<?php
/**
 * 실시간 환율 API (한국수출입은행)
 * GET /api/budget/exchange_rate - 주요 통화 KRW 환율 조회
 *
 * 지원 통화: USD, EUR, JPY, CNH, GBP, AUD, CAD, HKD, SGD, THB
 * JPY는 한수은 API가 100엔 기준이므로 1엔 단위로 변환
 * 환율 기준: 전신환 매도율(TTS) - 해외 카드 결제 시 카드사 적용 기준
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, null, '허용되지 않는 요청입니다.', 405);
}

$authKey = $_ENV['AUTH_KEY'] ?? '';
if (empty($authKey)) {
    jsonResponse(false, null, '환율 API 키가 설정되지 않았습니다. .env에 AUTH_KEY를 추가해주세요.', 500);
}

// 오늘 날짜로 조회, 없으면 최근 영업일(최대 7일)로 fallback
$date    = date('Ymd');
$rates   = [];
$lastErr = '';

foreach (array_merge([0], range(1, 7)) as $daysAgo) {
    $tryDate = date('Ymd', strtotime("-{$daysAgo} day"));
    [$rates, $lastErr] = fetchAllRates($authKey, $tryDate);
    if (!empty($rates)) {
        $date = $tryDate;
        break;
    }
}

if (empty($rates)) {
    jsonResponse(false, ['debug' => $lastErr], '환율 정보를 불러올 수 없습니다.', 503);
}

jsonResponse(true, [
    'rates'      => $rates,
    'date'       => $date,
    'date_label' => date('Y.m.d', strtotime($date)),
]);

/**
 * 한국수출입은행 API에서 지원 통화 전체 환율 조회 (cURL 사용)
 * @return array{array<string,float>, string}  [rates, errorMessage]
 */
function fetchAllRates(string $authKey, string $date): array
{
    $targets = [
        'USD'      => ['code' => 'USD', 'divisor' => 1],
        'EUR'      => ['code' => 'EUR', 'divisor' => 1],
        'JPY(100)' => ['code' => 'JPY', 'divisor' => 100],
        'CNH'      => ['code' => 'CNH', 'divisor' => 1],
        'GBP'      => ['code' => 'GBP', 'divisor' => 1],
        'AUD'      => ['code' => 'AUD', 'divisor' => 1],
        'CAD'      => ['code' => 'CAD', 'divisor' => 1],
        'HKD'      => ['code' => 'HKD', 'divisor' => 1],
        'SGD'      => ['code' => 'SGD', 'divisor' => 1],
        'THB'      => ['code' => 'THB', 'divisor' => 1],
    ];

    $url = 'https://www.koreaexim.go.kr/site/program/financial/exchangeJSON'
         . '?authkey=' . urlencode($authKey)
         . '&searchdate=' . urlencode($date)
         . '&data=AP01';

    // cURL 사용 (allow_url_fopen 비활성화 환경에서도 동작)
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'WithPlan/1.0',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ]);
        $body    = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $curlErr) {
            return [[], 'cURL error: ' . $curlErr];
        }
        if ($httpCode !== 200) {
            return [[], 'HTTP ' . $httpCode];
        }
    } else {
        // cURL 없을 때 file_get_contents fallback
        $ctx  = stream_context_create([
            'http' => ['timeout' => 8, 'ignore_errors' => true],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            return [[], 'file_get_contents failed (allow_url_fopen 확인 필요)'];
        }
    }

    $items = json_decode($body, true);

    // 한수은 API: 날짜 데이터 없을 때 빈 배열 또는 {"result":2} 반환
    if (!is_array($items) || empty($items) || isset($items['result'])) {
        return [[], 'no data for ' . $date . ' (body: ' . substr($body, 0, 100) . ')'];
    }

    $rates = [];
    foreach ($items as $item) {
        $curUnit = $item['cur_unit'] ?? '';
        if (!isset($targets[$curUnit])) {
            continue;
        }

        // 전신환 매도율(tts) 우선, 없으면 매매 기준율(deal_bas_r) fallback
        $tts     = str_replace(',', '', $item['tts'] ?? '');
        $rateStr = ($tts !== '' && (float) $tts > 0)
            ? $tts
            : str_replace(',', '', $item['deal_bas_r'] ?? '');
        $rateVal = (float) $rateStr;

        if ($rateVal <= 0) {
            continue;
        }

        $info             = $targets[$curUnit];
        $rates[$info['code']] = round($rateVal / $info['divisor'], 4);
    }

    return [$rates, ''];
}
