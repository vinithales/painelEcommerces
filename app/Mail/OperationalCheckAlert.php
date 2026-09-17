<?php

namespace App\Mail;

use App\Models\StoreCheckAlert;
use Illuminate\Mail\Mailable;

class OperationalCheckAlert extends Mailable
{
    public function __construct(public StoreCheckAlert $alert) {}

    public function build()
    {
        $check = $this->alert->check;

        return $this->subject('[BringPainel] '.$check->store->name.' — '.$this->alert->to_status)->view('emails.operational-check-alert');
    }
}
