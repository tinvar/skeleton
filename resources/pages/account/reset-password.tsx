import { Link } from '@tinvar/react/link';
import type { PageProps } from '../../types/generated';
import { AuthCard } from '../../components/account/AuthCard';
import { ResetPasswordForm } from '../../components/account/AccountForms';

/** Where a reset link lands: whose account, and the new password's form. */
export default function Page({ data }: PageProps<'account/reset-password'>) {
  if (data.token === '') {
    return (
      <AuthCard
        title="This link can't be used"
        lead="It has expired, has already been used, or was replaced by a newer one."
      >
        <Link href="/password/forgot">Ask for a new link</Link>
      </AuthCard>
    );
  }

  return (
    <AuthCard
      title="Choose a new password"
      lead={`For ${data.email ?? ''}. Every other device signed in to the account is signed out.`}
    >
      <ResetPasswordForm token={data.token} minLength={data.minLength} />
    </AuthCard>
  );
}
