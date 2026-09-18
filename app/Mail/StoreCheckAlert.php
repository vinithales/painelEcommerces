<?php

namespace App\Mail;

use App\Models\Store;
use Illuminate\Mail\Markdown;
use Mailtrap\MailtrapClient;
use Mailtrap\Mime\MailtrapEmail;
use RuntimeException;
use Symfony\Component\Mime\Address;

class StoreCheckAlert
{
    public function __construct(
        private readonly Store $store,
        private readonly array $failingResults,
    ) {
    }

    public function send(array $recipients): void
    {
        $recipientList = array_values(array_filter($recipients));
        $apiKey = (string) config('services.mailtrap.token');

        if ($recipientList === []) {
            return;
        }

        if ($apiKey === '') {
            throw new RuntimeException('MAILTRAP_API_KEY nao configurado.');
        }

        $markdown = app(Markdown::class);
        $viewData = [
            'store' => $this->store,
            'failingResults' => $this->failingResults,
        ];

        $email = (new MailtrapEmail())
            ->from(new Address(
                (string) config('mail.from.address'),
                (string) config('mail.from.name')
            ))
            ->subject("[BeaconHub] Alerta de SEO: {$this->store->name}")
            ->html($markdown->render('emails.store-check-alert', $viewData)->toHtml())
            ->text($markdown->renderText('emails.store-check-alert', $viewData)->toHtml());

        foreach ($recipientList as $recipient) {
            $email->to(new Address($recipient));
        }

        MailtrapClient::initSendingEmails(apiKey: $apiKey)->send($email);
    }
}
