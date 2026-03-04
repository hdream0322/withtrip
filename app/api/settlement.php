<?php
/**
 * 정산 API
 * GET /api/settlement?trip_code=xxx - 정산 데이터 계산 결과 반환
 */

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $tripCode      = $_GET['trip_code'] ?? '';
    $paymentFilter = $_GET['payment_method'] ?? 'all'; // all | card | cash
    $groupByDate   = ($_GET['group_by'] ?? '') === 'date';

    if (empty($tripCode)) {
        jsonResponse(false, null, '여행 코드가 필요합니다.', 400);
    }

    if (!in_array($paymentFilter, ['all', 'card', 'cash'], true)) {
        $paymentFilter = 'all';
    }

    $db = getDB();

    // 여행 존재 확인
    $trip = getTripByCode($db, $tripCode);
    if (!$trip) {
        jsonResponse(false, null, '존재하지 않는 여행입니다.', 404);
    }

    // 멤버 목록
    $members = getTripMembers($db, $tripCode);
    $memberMap = [];
    foreach ($members as $member) {
        $memberMap[$member['user_id']] = $member['display_name'];
    }

    // 지출 내역 조회 (결제 방법 필터 적용)
    if ($paymentFilter === 'all') {
        $stmt = $db->prepare(
            'SELECT * FROM expenses
             WHERE trip_code = ?
             ORDER BY expense_date DESC, created_at DESC'
        );
        $stmt->execute([$tripCode]);
    } else {
        $stmt = $db->prepare(
            'SELECT * FROM expenses
             WHERE trip_code = ? AND (payment_method = ? OR payment_method IS NULL AND ? = \'card\')
             ORDER BY expense_date DESC, created_at DESC'
        );
        $stmt->execute([$tripCode, $paymentFilter, $paymentFilter]);
    }
    $expenses = $stmt->fetchAll();

    // 더치페이 분담 내역 조회
    $stmt = $db->prepare('SELECT * FROM dutch_splits WHERE trip_code = ?');
    $stmt->execute([$tripCode]);
    $allSplits = $stmt->fetchAll();

    // expense_id별 분담 내역 맵핑
    $splitsByExpense = [];
    foreach ($allSplits as $split) {
        $splitsByExpense[$split['expense_id']][] = $split;
    }

    // 현금 환전자 정보 조회
    $stmt = $db->prepare(
        'SELECT currency, cash_exchanger_id, cash_rate
         FROM trip_exchange_rates
         WHERE trip_code = ? AND cash_exchanger_id IS NOT NULL AND cash_rate IS NOT NULL'
    );
    $stmt->execute([$tripCode]);
    $cashExchangerRows = $stmt->fetchAll();
    $cashExchangers = [];
    foreach ($cashExchangerRows as $row) {
        $cashExchangers[$row['currency']] = $row['cash_exchanger_id'];
    }

    // 통화별 사용 여부 확인
    $currencies = [];
    foreach ($expenses as $exp) {
        $currencies[$exp['currency']] = true;
    }
    $currencyList = array_keys($currencies);
    $hasMultipleCurrencies = count($currencyList) > 1;

    // 각 멤버별 통화별·결제수단별 지출/부담 계산
    // paid: 실제 결제한 금액, owed: 부담해야 할 금액
    // payment_method별로 분리하여 저장 (통합 정산 시 환율 구분 용도)
    $balanceByCurrencyAndMethod = [];

    foreach ($expenses as $exp) {
        $currency = $exp['currency'];
        $paidBy = $exp['paid_by'];
        $amount = (int) $exp['amount'];
        $isDutch = (int) $exp['is_dutch'];
        $paymentMethod = $exp['payment_method'] ?? 'card'; // 기본값: 카드

        // 키: "currency:payment_method"
        $key = $currency . ':' . $paymentMethod;

        if (!isset($balanceByCurrencyAndMethod[$key])) {
            $balanceByCurrencyAndMethod[$key] = [];
        }

        // 현금 + 환전자 지정 시: paid를 환전자에게 귀속
        $effectivePaidBy = $paidBy;
        if ($paymentMethod === 'cash' && isset($cashExchangers[$currency])) {
            $effectivePaidBy = $cashExchangers[$currency];
        }

        // 결제자(또는 환전자)의 지출 기록
        if (!isset($balanceByCurrencyAndMethod[$key][$effectivePaidBy])) {
            $balanceByCurrencyAndMethod[$key][$effectivePaidBy] = ['paid' => 0, 'owed' => 0];
        }
        $balanceByCurrencyAndMethod[$key][$effectivePaidBy]['paid'] += $amount;

        if ($isDutch && isset($splitsByExpense[$exp['id']])) {
            // 더치페이: 분담 내역에 따라 각자 부담
            foreach ($splitsByExpense[$exp['id']] as $split) {
                $splitUser = $split['user_id'];
                if (!isset($balanceByCurrencyAndMethod[$key][$splitUser])) {
                    $balanceByCurrencyAndMethod[$key][$splitUser] = ['paid' => 0, 'owed' => 0];
                }
                $balanceByCurrencyAndMethod[$key][$splitUser]['owed'] += (int) $split['amount'];
            }
        } else {
            // 더치페이 아님: 결제자 단독 부담
            $balanceByCurrencyAndMethod[$key][$paidBy]['owed'] += $amount;
        }
    }

    // 호환성을 위해 통화별로도 유지
    $balanceByCurrency = [];
    foreach ($balanceByCurrencyAndMethod as $key => $userBalances) {
        list($currency, $paymentMethod) = explode(':', $key);
        if (!isset($balanceByCurrency[$currency])) {
            $balanceByCurrency[$currency] = [];
        }
        foreach ($userBalances as $uid => $bal) {
            if (!isset($balanceByCurrency[$currency][$uid])) {
                $balanceByCurrency[$currency][$uid] = ['paid' => 0, 'owed' => 0];
            }
            $balanceByCurrency[$currency][$uid]['paid'] += $bal['paid'];
            $balanceByCurrency[$currency][$uid]['owed'] += $bal['owed'];
        }
    }

    // 통화별 net balance 계산 및 최소 이체 알고리즘
    $settlementsByCurrency = [];

    foreach ($balanceByCurrency as $currency => $userBalances) {
        $netBalances = [];
        foreach ($userBalances as $uid => $bal) {
            $net = $bal['paid'] - $bal['owed'];
            if ($net !== 0) {
                $netBalances[$uid] = $net;
            }
        }

        // 최소 이체 알고리즘
        $transfers = calculateMinTransfers($netBalances);

        $settlementsByCurrency[$currency] = [
            'balances'  => $userBalances,
            'transfers' => $transfers,
        ];
    }

    // 멤버별 요약 (통화별)
    $memberSummary = [];
    foreach ($members as $member) {
        $uid = $member['user_id'];
        $summary = [
            'user_id'      => $uid,
            'display_name' => $member['display_name'],
            'is_owner'     => (bool) $member['is_owner'],
            'by_currency'  => [],
        ];

        foreach ($balanceByCurrency as $currency => $userBalances) {
            $bal = $userBalances[$uid] ?? ['paid' => 0, 'owed' => 0];
            $summary['by_currency'][$currency] = [
                'paid' => $bal['paid'],
                'owed' => $bal['owed'],
                'net'  => $bal['paid'] - $bal['owed'],
            ];
        }

        $memberSummary[] = $summary;
    }

    // 날짜별 그룹 정산
    $groupedByDate = null;
    if ($groupByDate) {
        $expensesByDate = [];
        foreach ($expenses as $exp) {
            $dateKey = (isset($exp['expense_date']) && $exp['expense_date'] !== '' && $exp['expense_date'] !== null)
                ? $exp['expense_date']
                : 'null';
            $expensesByDate[$dateKey][] = $exp;
        }

        // 날짜 오름차순, 'null'은 맨 마지막
        uksort($expensesByDate, function ($a, $b) {
            if ($a === 'null') return 1;
            if ($b === 'null') return -1;
            return strcmp($a, $b);
        });

        $groupedByDate = [];
        foreach ($expensesByDate as $dateKey => $dateExpenses) {
            $dateByCurrencyAndMethod = [];

            foreach ($dateExpenses as $exp) {
                $currency      = $exp['currency'];
                $paidBy        = $exp['paid_by'];
                $amount        = (int) $exp['amount'];
                $isDutch       = (int) $exp['is_dutch'];
                $paymentMethod = $exp['payment_method'] ?? 'card';
                $key           = $currency . ':' . $paymentMethod;

                if (!isset($dateByCurrencyAndMethod[$key])) {
                    $dateByCurrencyAndMethod[$key] = [];
                }

                $effectivePaidBy = $paidBy;
                if ($paymentMethod === 'cash' && isset($cashExchangers[$currency])) {
                    $effectivePaidBy = $cashExchangers[$currency];
                }

                if (!isset($dateByCurrencyAndMethod[$key][$effectivePaidBy])) {
                    $dateByCurrencyAndMethod[$key][$effectivePaidBy] = ['paid' => 0, 'owed' => 0];
                }
                $dateByCurrencyAndMethod[$key][$effectivePaidBy]['paid'] += $amount;

                if ($isDutch && isset($splitsByExpense[$exp['id']])) {
                    foreach ($splitsByExpense[$exp['id']] as $split) {
                        $splitUser = $split['user_id'];
                        if (!isset($dateByCurrencyAndMethod[$key][$splitUser])) {
                            $dateByCurrencyAndMethod[$key][$splitUser] = ['paid' => 0, 'owed' => 0];
                        }
                        $dateByCurrencyAndMethod[$key][$splitUser]['owed'] += (int) $split['amount'];
                    }
                } else {
                    if (!isset($dateByCurrencyAndMethod[$key][$paidBy])) {
                        $dateByCurrencyAndMethod[$key][$paidBy] = ['paid' => 0, 'owed' => 0];
                    }
                    $dateByCurrencyAndMethod[$key][$paidBy]['owed'] += $amount;
                }
            }

            // 통화별로 합산
            $dateByCurrency = [];
            $dateCurrencies = [];
            foreach ($dateByCurrencyAndMethod as $key => $userBalances) {
                [$currency, ] = explode(':', $key);
                if (!in_array($currency, $dateCurrencies, true)) {
                    $dateCurrencies[] = $currency;
                }
                foreach ($userBalances as $uid => $bal) {
                    if (!isset($dateByCurrency[$currency][$uid])) {
                        $dateByCurrency[$currency][$uid] = ['paid' => 0, 'owed' => 0];
                    }
                    $dateByCurrency[$currency][$uid]['paid'] += $bal['paid'];
                    $dateByCurrency[$currency][$uid]['owed'] += $bal['owed'];
                }
            }

            // 날짜별 최소 이체 계산
            $dateSettlements = [];
            foreach ($dateByCurrency as $currency => $userBalances) {
                $netBalances = [];
                foreach ($userBalances as $uid => $bal) {
                    $net = $bal['paid'] - $bal['owed'];
                    if ($net !== 0) $netBalances[$uid] = $net;
                }
                $transfers = calculateMinTransfers($netBalances);
                $dateSettlements[$currency] = [
                    'balances'  => $userBalances,
                    'transfers' => $transfers,
                ];
            }

            $groupedByDate[$dateKey] = [
                'label'                          => $dateKey === 'null' ? '날짜 미정' : $dateKey,
                'expense_count'                  => count($dateExpenses),
                'currencies'                     => $dateCurrencies,
                'settlements'                    => $dateSettlements,
                'balance_by_currency_and_method' => $dateByCurrencyAndMethod,
            ];
        }
    }

    jsonResponse(true, [
        'members'       => $memberSummary,
        'settlements'   => $settlementsByCurrency,
        'currencies'    => $currencyList,
        'has_multiple'  => $hasMultipleCurrencies,
        'member_names'  => $memberMap,
        'balance_by_currency_and_method' => $balanceByCurrencyAndMethod,
        'cash_exchangers' => $cashExchangers,
        'grouped_by_date' => $groupedByDate,
    ]);
}

jsonResponse(false, null, '지원하지 않는 요청입니다.', 405);

/**
 * 최소 이체 알고리즘
 * net balance가 양수인 사람(받을 사람)과 음수인 사람(줄 사람)을 매칭
 *
 * @param array $netBalances [user_id => net_amount, ...]
 * @return array [['from' => user_id, 'to' => user_id, 'amount' => int], ...]
 */
function calculateMinTransfers(array $netBalances): array
{
    $creditors = []; // 받을 사람 (양수)
    $debtors = [];   // 줄 사람 (음수)

    foreach ($netBalances as $uid => $amount) {
        if ($amount > 0) {
            $creditors[] = ['user_id' => $uid, 'amount' => $amount];
        } elseif ($amount < 0) {
            $debtors[] = ['user_id' => $uid, 'amount' => abs($amount)];
        }
    }

    // 금액 내림차순 정렬 (큰 금액부터 매칭)
    usort($creditors, fn($a, $b) => $b['amount'] - $a['amount']);
    usort($debtors, fn($a, $b) => $b['amount'] - $a['amount']);

    $transfers = [];
    $ci = 0;
    $di = 0;

    while ($ci < count($creditors) && $di < count($debtors)) {
        $transferAmount = min($creditors[$ci]['amount'], $debtors[$di]['amount']);

        if ($transferAmount > 0) {
            $transfers[] = [
                'from'   => $debtors[$di]['user_id'],
                'to'     => $creditors[$ci]['user_id'],
                'amount' => $transferAmount,
            ];
        }

        $creditors[$ci]['amount'] -= $transferAmount;
        $debtors[$di]['amount'] -= $transferAmount;

        if ($creditors[$ci]['amount'] === 0) {
            $ci++;
        }
        if ($debtors[$di]['amount'] === 0) {
            $di++;
        }
    }

    return $transfers;
}
