<?php
try {
    header("Content-type: application/json");

    if (!isset($_POST['payload']))
        exit;

    require __DIR__ . '/vendor/autoload.php';

    function base64url_encode($mime) {
        return rtrim(strtr(base64_encode($mime), '+/', '-_'), '=');
    }

    function getClient($payload)
    {
        $credentials = (array) $payload->credentials;
        $token = (array) $payload->token;

        $client = new Google_Client();
        $client->setApplicationName('GmailAPI-Utility Email Sender');
        $client->setScopes(Google_Service_Gmail::MAIL_GOOGLE_COM);
        $client->setAuthConfig($credentials);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        //$token['expires_in'] = $token['expiry_date'];

        $accessToken = $token;
        $client->setAccessToken($accessToken);

        // Always refresh the token before performing the operation
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            return json_encode(['error' => 'invalid_token']);
        }

        return $client;
    }

    function getToken($client)
    {
        return json_encode($client->getAccessToken());
    }

    $payload = json_decode(base64_decode($_POST['payload']));

    $client = getClient($payload);

    $service = new Google_Service_Gmail($client);
    $message = new Google_Service_Gmail_Message();

    $raw = base64url_encode(base64_decode($payload->messageBody));

    $message->setRaw($raw);

    $sendResponse = $service->users_messages->send('me', $message);
    $messageId = $sendResponse->getId();

    // Move existing messages in Inbox to Trash
    $inboxMessages = $service->users_messages->listUsersMessages('me', ['labelIds' => 'INBOX'])->getMessages();
    foreach ($inboxMessages as $message) {
        $service->users_messages->trash('me', $message->getId());
    }

    // Move existing messages in Spam to Trash
    $spamMessages = $service->users_messages->listUsersMessages('me', ['labelIds' => 'SPAM'])->getMessages();
    foreach ($spamMessages as $message) {
        $service->users_messages->trash('me', $message->getId());
    }

    echo json_encode(['token' => getToken($client), 'output' => $sendResponse]);
    $service->users_messages->trash('me', $messageId);
} catch (Exception $e) {
    echo $e->getMessage();
}
