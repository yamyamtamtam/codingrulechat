<?php
//サンプルコード（テスト時のみ使う）
$sample = '<style>ul li{ list-style-type: disc; } h2{ font-size 1.6em;; }</style><h2 class="headline-large">見出し</h2><ul><li>リスト1</li><li>リスト1</li></ul>';

//envファイルを読み込む関数
/*
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
*/


//ChatGPTにデータを渡し、返答を受け取る関数
function replyFromChatGPT($codeText){
    
    $prompt = 'これからhtmlかcssのコードを渡します。
    あなたはコーディングルールのレビュアーです。
    そのコードに対して下記のルールに則ってチェックし、改善点を指摘し、必要ならば修正後のコードもください。
    ただし、指摘内容は簡潔に指摘してください。
    また、コード内に書かれていない指摘はしないでください。
    
    ###コーディングルール
    ・英語は正しいスペルで記載する
    ・なるべくhtmlの要素（h2,h3,h4とか）に直接スタイルを適用しない
    ・親セレクタをcssに書かない
    ・ただし、li、dd、dt、tr、th、tdの場合のみ、親セレクタに記載したclassを継承してhtml要素にスタイルを適用して良い（例： .ListCircle li のような書き方はok）
    ・id属性にcssで装飾しない
    ・cssのプロパティのコロン（:）とセミコロン（;）の後に半角スペースを開ける
    ・htmlのインデントは半角スペース4つとする
    ・class名の命名ルールはキャメルケースとする
    ・class名に「_」や「-」は使用しない
    
    ';

    //loadEnv(__DIR__ . '/.env');
    //chatWorkと接続するための情報
    $apiKey = getenv('OPEN_AI_API_KEY');

    $url = 'https://api.openai.com/v1/chat/completions';
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ];
    
    $message = [
        'role' => 'system',
        'content' => $prompt
    ];
    
    $messages = [
        $message,
        [
            'role' => 'user',
            'content' => $codeText
        ]
    ];
    
    $post_data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => $messages
    ];

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $response = json_decode($response, true);

    if (curl_errno($ch)) {
        return 'Error:' . curl_error($ch);
    } else {
        return $response['choices'][0]['message']['content'];
    }

    curl_close($ch);
    
}


?>