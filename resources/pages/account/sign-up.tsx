import { Banner } from '@meridian/ui';
import { Link } from '@tinvar/react/link';
import type { PageProps } from '../../types/generated';
import { AuthCard } from '../../components/account/AuthCard';
import { SignUpForm } from '../../components/account/AccountForms';

/**
 * Signing up: an address and a name, then the email. After the form, the
 * page says to look in the inbox whatever the address -- an address that
 * already has an account gets an email saying so instead.
 */
export default function Page({ data }: PageProps<'account/sign-up'>) {
  return (
    <AuthCard
      title="Make an account"
      lead="We email you a link. Open it to choose a password, and the account is yours."
      below={<Link href="/sign-in">Already have an account? Sign in</Link>}
    >
      {data.sent ? (
        <Banner tone="good" title="Check your email">
          {`If the address can make an account, a link to finish is on its way. It works once, for a day. Your password will need at least ${data.minLength} characters.`}
        </Banner>
      ) : (
        <SignUpForm />
      )}
    </AuthCard>
  );
}
