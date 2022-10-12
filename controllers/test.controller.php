<?php
require_once './services/mailer.service.php';

class TestController{
    public function __construct(){
        $this->action = null;
        if($_SERVER["REQUEST_METHOD"] == "GET"){
            $this->action = $this->sendTestMail();
        }
    }
    function sendTestMail(){
        $ms = new MailerService();
        $mailParams = [
            "fromAddress" => ["newsletter@monblog.com", "newslmetter monblog.com"],
            "destAddresses" => ["dev.justine.verin@gmail.com"],
            "replyAddress" => ['info@monblog.com', "information monblog.com"],
            "subject" => "Newsletter monblog.com",
            "body" => "This is the HTML message sent by <b>monblog.com</b>. Do not forget to import the bootstrap css and js files into your projects.",
            "altBody" => "This is the plain text message for non-HTML mail clients. You shouldn't really worry about installing bootstrap or not."
        ];
        return $ms->send($mailParams);
    }
    
}