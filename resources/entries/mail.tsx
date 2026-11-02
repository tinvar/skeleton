import { createMailRenderer } from '@tinvar/mail/render';
import AccountAlreadyRegistered from '../emails/AccountAlreadyRegistered';
import AccountEmailChange from '../emails/AccountEmailChange';
import AccountEmailChanged from '../emails/AccountEmailChanged';
import AccountPasskeyAdded from '../emails/AccountPasskeyAdded';
import AccountPasswordChanged from '../emails/AccountPasswordChanged';
import AccountPasswordReset from '../emails/AccountPasswordReset';
import AccountSignUp from '../emails/AccountSignUp';
import AccountTwoFactorDisabled from '../emails/AccountTwoFactorDisabled';

/**
 * The email templates PHP may name, by the name its Mailable gives
 * (App\Mail\Account\AccountMails). A registry, as the deferred components
 * are: the name arrives in a frame, and resolving it to a module path would
 * make a string from PHP into a loader argument.
 *
 * Each is drawn by @tinvar/mail (ADR 0086) into the HTML mail clients read
 * and the plain-text part, every link written out -- which PHP checks
 * before it sends anything, with the links held to the app's origin.
 */
export const renderMail = createMailRenderer({
  'account-sign-up': AccountSignUp,
  'account-already-registered': AccountAlreadyRegistered,
  'account-password-reset': AccountPasswordReset,
  'account-email-change': AccountEmailChange,
  'account-email-changed': AccountEmailChanged,
  'account-password-changed': AccountPasswordChanged,
  'account-passkey-added': AccountPasskeyAdded,
  'account-two-factor-disabled': AccountTwoFactorDisabled,
});
