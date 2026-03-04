<?php
/**
 * 정산 API
 * GET /api/settlement?trip_code=xxx - 정산 데이터 계산 결과 반환
 */

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $tripCode      = $_GET['trip_code'] ?? '';
    $paymentFilter = $_GET['payment_method'] ?? 'all'; // all | card | cash

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

        // 결제자의 지출 기록
        if (!isset($balanceByCurrencyAndMethod[$key][$paidBy])) {
            $balanceByCurrencyAndMethod[$key][$paidBy] = ['paid' => 0, 'owed' => 0];
        }
        $balanceByCurrencyAndMethod[$key][$paidBy]['paid'] += $amount;

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

    jsonResponse(true, [
        'members'       => $memberSummary,
        'settlements'   => $settlementsByCurrency,
        'currencies'    => $currencyList,
        'has_multiple'  => $hasMultipleCurrencies,
        'member_names'  => $memberMap,
        'balance_by_currency_and_method' => $balanceByCurrencyAndMethod, // 통합 정산 시 payment_method 구분용
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
