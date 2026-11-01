import { Banner } from '@meridian/ui';
import { Link } from '@tinvar/react/link';
import type { PageProps } from '../../types/generated';
import { AuthCard } from '../../components/account/AuthCard';
import { ForgotPasswordForm } from '../../components/account/AccountForms';

/** Asking for a reset link: the same answer whatever the address. */
export default function Page({ data }: PageProps<'account/forgot-password'>) {
  return (
    <AuthCard
      title="Reset your password"
      lead="We email you a link to choose a new one."
      below={<Link href="/sign-in">Back to sign in</Link>}
    >
      {data.sent ? (
        <Banner tone="good" title="Check your email">
          If the address has an account, a reset link is on its way. It works once, for 15 minutes.
        </Banner>
      ) : (
        <ForgotPasswordForm />
      )}
    </AuthCard>
  );
}
