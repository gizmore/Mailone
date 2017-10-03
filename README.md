# MailOnE
MailOnError is a single file you can include to get error mails for tricky PHP errors.
These errors mostly are: White screen of death in frameworks.

It is also useful to get error mails for any pedantic php error, get mails on fatals, and watch the health of your sites by reducing errors.
The error mails consist of a stacktrace and GPRC values.

Remote SMTP support is planned.

Enjoy!


## Example from HEAD

    define('MAILONE_MAIL_TO', 'gizmore@wechall.net');
    include 'mailone.php';
    someblackbox();
    include 'mailone.php'; # re-force! (should not be necessary.... but you never know)
    moreblackbox();
