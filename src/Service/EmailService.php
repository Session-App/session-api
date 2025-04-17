<?php

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailService
{
    public function __construct(MailerInterface $mailer, TranslatorInterface $translator)
    {
        $this->mailer = $mailer;
        $this->translator = $translator;
    }

    public function send(
        $user,
        $context
    ): void {

        $context['locale'] = $user->getLang();
        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['MAILER_FROM_ADDRESS'], $_ENV['MAILER_FROM_NAME']))
            ->to($user->getEmail())
            ->subject($this->translator->trans('emails.' . $context['email_name'] . '.subject', [], 'messages', $context['locale']))
            ->htmlTemplate('emails/' . $context['email_name'] . '.html.twig')
            ->context($context);

        $this->mailer->send($email);
    }

    private function getMailer(): string
    {
        $mailer = '';
        switch (get_class($this->mailer)) {
            case 'App\\Domain\\Email\\MailjetMailer':
                $mailer = 'mailjet';
                break;
            default:
                $mailer = 'default';
                break;
        }
        return $mailer;
    }
}
