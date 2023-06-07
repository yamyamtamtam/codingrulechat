<?php
include (__DIR__ . '/codingRuleToChatGPT.php');

//envファイルを読み込む関数
function loadEnv($path) {
    if (!file_exists($path)) {
        echo '.env file does not exist';
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = array();
    foreach ($lines as $line) {
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (isset($name)) {
            putenv($name . '=' . $value);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    loadEnv(__DIR__ . '/.env');
    //chatWorkと接続するための情報
    $webhookToken = getenv('CHATWORK_WEBHOOK_TOKEN');
    $apiSecretKey = getenv('CHATWORK_API_SECRET_KEY');
 
    /**
     * ChatWorkのWebhookからのデータ受け取り箇所
     */
    // Chatwork Webhook秘密鍵（トークン）をBase64デコードして取得
    $secretKey = base64_decode($webhookToken);
    // リクエストボディを取得
    $requestBody = file_get_contents('php://input');
    // リクエストから受け取った署名（ヘッダまたはリクエストパラメータ）を取得
    $receivedSignature = isset($_SERVER['HTTP_X_CHATWORKWEBHOOKSIGNATURE']) ? $_SERVER['HTTP_X_CHATWORKWEBHOOKSIGNATURE'] : $_GET['chatwork_webhook_signature'];
    // HMAC-SHA256でダイジェスト値を計算
    $calculatedSignature = base64_encode(hash_hmac('sha256', $requestBody, $secretKey, true));
    $response = '';

    // 署名の一致を確認
    if ($receivedSignature === $calculatedSignature) {
        // リクエストが正当な場合の処理
        http_response_code(200);
        $response = '200';
        $eventType = '';
        $messageFromChatWork = '';
        $accountFromChatWork = '';
        $roomIdFromChatWork = '';
        $sendAccountFromChatWork = '';
        $data = json_decode($requestBody, true);
        //変数にデータを格納
        if (!empty($data)) {
            foreach($data as $key => $value){
                if ($key == 'webhook_event_type') {
                    $eventType = $value;
                }
                if ($key == 'webhook_event') {
                    $messageFromChatWork = $value['body'];
                    $messageIdFromChatWork = $value['message_id'];
                    $roomIdFromChatWork = $value['room_id'];
                    $sendAccountFromChatWork = $value['account_id'];
                } 
            }
        }
        //メッセージ作成・編集の時で、リプライ以外の時にチャットワークに投稿(
        if($eventType == 'message_created' && strpos($messageFromChatWork, '[rp') !== 0){
            $messageTo = replyFromChatGPT($messageFromChatWork);
            postMessageToChatwork($roomIdFromChatWork , $messageTo , $messageIdFromChatWork , $sendAccountFromChatWork , $apiSecretKey);
        }
    } else {
        // 署名が一致しない場合の処理
        http_response_code(403);
        $response = mailSend('403 署名が一致しません。');
    }
}
//メール送信でデバッグ
function mailSend($text){
    $to = 'cpcweb02@gmail.com';
    $subject = 'cpcrd/codingcheckhub/chatwork.phpの通知';
    $message = $text;
    $headers = 'From: cpcweb02@gmail.com';
    if (mail($to, $subject, $message, $headers)) {
        //echo 'メールが送信されました。';
    } else {
        //echo 'メールの送信に失敗しました。';
    }
}

//chatWorkにデータを投稿する関数
function postMessageToChatwork($roomID, $message, $messageId, $account, $apiKey) {
    $url = 'https://api.chatwork.com/v2/rooms/' . $roomID . '/messages';
    $headers = array(
        'X-ChatWorkToken: ' . $apiKey,
    );
    $reply = '[rp aid=' . $account . ' to=' . $roomID . '-' . $messageId . ']' . PHP_EOL;
    $reply .=  'ChatGPTからの返答は下記です。' . PHP_EOL;
    $reply .=  '-------------------------' . PHP_EOL;
    $reply .=  $message;
    $data = array(
        'body' => $reply
    );
    $options = array(
        'http' => array(
            'header'  => implode("\r\n", $headers),
            'method'  => 'POST',
            'content' => http_build_query($data),
        ),
    );
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    if ($result === FALSE) {
        return false;
        mailSend('error');
    }
    return true;
}
?>