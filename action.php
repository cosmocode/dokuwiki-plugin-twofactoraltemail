<?php

use dokuwiki\Form\Form;
use dokuwiki\plugin\twofactor\Provider;

/**
 * 2fa provider using an alternative email address
 */
class action_plugin_twofactoraltemail extends Provider
{
    /** @inheritdoc */
    public function getLabel()
    {
        return $this->getLang('name');
    }

    /** @inheritdoc */
    public function isConfigured()
    {
        return $this->settings->get('email') &&
            $this->settings->get('verified');

    }

    /** @inheritdoc */
    public function renderProfileForm(Form $form)
    {
        $email = $this->settings->get('email');
        $verified = $this->settings->get('verified');

        if (!$email) {
            $form->addHTML('<p>' . $this->getLang('intro') . '</p>');
            $form->addTextInput('newemail', $this->getLang('email'))->attr('autocomplete', 'off');
        } else {
            if (!$verified) {
                $form->addHTML('<p>' . $this->getLang('verifynotice') . '</p>');
                $form->addTextInput('verify', $this->getLang('verifymodule'));
            } else {
                $form->addHTML(sprintf('<p>' . $this->getLang('configured') . '</p>', hsc($email)));
            }
        }

        return $form;
    }

    /** @inheritdoc */
    public function handleProfileForm()
    {
        global $INPUT;
        global $USERINFO;

        if ($INPUT->str('verify')) {
            // verification code given, check the code
            if ($this->checkCode($INPUT->str('verify'))) {
                $this->settings->set('verified', true);
            } else {
                $this->settings->delete('email');
            }
        } elseif ($INPUT->str('newemail')) {
            $newmail = $INPUT->str('newemail');
            // check that it differs
            if (strtolower($newmail) == strtolower($USERINFO['mail'])) {
                msg($this->getLang('notsameemail'), -1);
                return;
            }

            // new email has been, set init verification
            $this->settings->set('email', $newmail);

            try {
                $this->initSecret();
                $code = $this->generateCode();
                $info = $this->transmitMessage($code);
                msg(hsc($info), 1);
            } catch (\Exception $e) {
                msg(hsc($e->getMessage()), -1);
                $this->settings->delete('email');
            }
        }
    }

    /** @inheritdoc */
    public function getTolerance()
    {
        return $this->getConf('tolerance');
    }

    /**
     * @inheritdoc
     * @todo localize
     */
    public function transmitMessage($code)
    {
        $to = $this->settings->get('email');
        if (!$to) throw new \Exception('No email set');

        // Create the email object.
        $mail = new Mailer();
        $mail->to($to);
        $mail->subject('Your OTP code');
        $mail->setText('Your code: ' . $code);
        $result = $mail->send();
        if (!$result) throw new \Exception('Email couldnt be sent');

        return 'A one time code has been send. Please check your email';
    }
}
