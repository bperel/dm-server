<?php
namespace App\Helper\Email;

class BookstoreSuggestedEmail extends AbstractEmail {

    protected function getFrom() : string {
        return $this->user->getUsername(). '@' .$_ENV['SMTP_ORIGIN_EMAIL_DOMAIN_DUCKSMANAGER'];
    }

    protected function getFromName() : string {
        return $this->user->getUsername(). '@' .$_ENV['SMTP_ORIGIN_EMAIL_DOMAIN_DUCKSMANAGER'];
    }

    public function getTo() : string {
        return $_ENV['SMTP_USERNAME'];
    }

    protected function getToName() : string {
        return $_ENV['SMTP_FRIENDLYNAME'];
    }

    public function getSubject() : string {
        return 'Ajout de bouquinerie';
    }

    protected function getTextBody() : string {
        return "Validation : {$_ENV['WEBSITE_ROOT']}/backend/bouquineries.php";
    }

    protected function getHtmlBody() : string {
        return '<a href="'.$_ENV['WEBSITE_ROOT'].'/backend/bouquineries.php">Validation</a>';
    }

    public function __toString() : string {
        return "user {$this->user->getUsername()} suggested a bookcase";
    }
}
