<?php

namespace App\Service;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

class PushNotificationsService
{
    // private Messaging $messaging;

    public function __construct(Messaging $messaging, TranslatorInterface $translator)
    {
        $this->messaging = $messaging;
        $this->translator = $translator;
    }

    public function sendNotifications($users, $title, $body, $bodyVars, $notifData, $onlyIfLoggedIn = true): bool
    {
        if ($_ENV['APP_ENV'] == 'dev') {
            return false;
        }
        $tokensByLanguage = [];
        foreach ($users as $user) {
            $pushNotificationsToken = $user->getPushNotificationsToken();
            if ($pushNotificationsToken && $onlyIfLoggedIn ? !$user->isLoggedOut() : true) {
                $tokensByLanguage[$user->getLang() ? $user->getLang() : 'en'][] = $pushNotificationsToken;
            }
        }

        foreach ($tokensByLanguage as $lang => $deviceTokens) {
            // todo : check that the list $deviceTokens is smaller than 500 items : https://firebase-php.readthedocs.io/en/latest/cloud-messaging.html#send-multiple-messages-at-once
            $notification = ['title' => /* $title */ 'Session', 'body' => $this->translator->trans('notifications.' . $body, $bodyVars, 'messages', $lang)];
            $message = CloudMessage::new()->withNotification($notification)->withData($notifData);
            $this->messaging->sendMulticast($message, $deviceTokens);
        }

        return true;
    }
}
