<?php

namespace App\Mail;

use App\Models\Member;
use App\Models\MemberLoginToken;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** 生徒さんへのログイン用リンク。すぐ届くことが大事なので、キューを通さずにその場で送る */
class MemberLoginLink extends Mailable
{
    use Queueable;

    public function __construct(public readonly Member $member, public readonly string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '【つちのね】ログイン用のリンク');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.member-login', with: ['minutes' => MemberLoginToken::LIFETIME_MINUTES]);
    }
}
