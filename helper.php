<?php

use dokuwiki\Form\Form;
use dokuwiki\plugin\twofactor\Provider;

/**
 * 2fa provider using an alternative email address
 */
class helper_plugin_twofactoraltemail extends Provider
{
    /** @inheritdoc */
    public function getLabel()
    {
        return 'Alternative E-Mail Address'; // FIXME localize
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
            $form->addTextInput('newemail', $this->getLang('email'))->attr('autocomplete', 'off');
        } else {
            if (!$verified) {
                $form->addHTML('<p>' . $this->getLang('verifynotice') . '</p>');
                $form->addTextInput('verify', $this->getLang('verifymodule'));
            } else {
                $form->addHTML('<p>Using email <code>' . hsc($email) . '</code></p>'); // FIXME localize
            }
        }

        return $form;
    }

    /** @inheritdoc */
    public function handleProfileForm()
    {
        global $INPUT;

        if ($INPUT->str('verify')) {
            // verification code given, check the code
            if ($this->checkCode($INPUT->str('verify'))) {
                $this->settings->set('verified', true);
            } else {
                msg('Verification failed', -1);
                $this->settings->delete('email');
            }
        } elseif ($INPUT->str('newemail')) {
            // new email has been, set init verification
            $this->settings->set('email', $INPUT->str('newemail'));

            // fixme move this to a base function?
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












    // region old shit

    /**
     * If the user has a valid email address in their profile, then this can be used.
     */
    public function canUse($user = null)
    {
        global $USERINFO;
        return ($this->_settingExists("verified", $user) && (empty($USERINFO) || $this->_settingGet("email", '',
                    $user) != $USERINFO['mail']) && $this->getConf('enable') === 1);
    }

    /**
     * This module can not provide authentication functionality at the main login screen.
     */
    public function canAuthLogin()
    {
        return false;
    }

    /**
     * Process any user configuration.
     */
    public function processProfileForm()
    {
        global $INPUT, $USERINFO;
        if ($INPUT->bool('altemail_disable', false)) {
            // Delete the email address.
            $this->_settingDelete("email");
            // Delete the verified setting.
            $this->_settingDelete("verified");
            return 'deleted';
        }
        $oldemail = $this->_settingGet("email", '');
        if ($oldemail) {
            if ($INPUT->bool('altemail_send', false)) {
                return 'otp';
            }
            $otp = $INPUT->str('altemail_verify', '');
            if ($otp) { // The user will use email.
                $checkResult = $this->processLogin($otp);
                // If the code works, then flag this account to use email.
                if ($checkResult == false) {
                    return 'failed';
                } else {
                    $this->_settingSet("verified", true);
                    return 'verified';
                }
            }
        }

        $changed = null;
        $email = $INPUT->str('altemail_email', '');
        if ($email != $oldemail) {
            if ($email == $USERINFO['mail']) {
                msg($this->getLang('notsameemail'), -1);
            } else {
                if ($this->_settingSet("email", $email) == false) {
                    msg("TwoFactor: Error setting alternate email.", -1);
                }
                // Delete the verification for the email if it was changed.
                $this->_settingDelete("verified");
                $changed = true;
            }
        }

        // If the data changed and we have everything needed to use this module, send an otp.
        if ($changed && $this->_settingExists("email")) {
            $changed = 'otp';
        }
        return $changed;
    }

    /**
     * This module can send messages.
     */
    public function canTransmitMessage()
    {
        return true;
    }



    /**
     *    This module uses the default authentication.
     */
    //public function processLogin($code);

    //endregion
}
