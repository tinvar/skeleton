import { Link } from '@tinvar/react/link';
import type { PageProps } from '../../types/generated';
import { AuthCard } from '../../components/account/AuthCard';
import { ConfirmEmailForm } from '../../components/account/AccountForms';

/** Where a change-of-address link lands: from and to, and the button that moves it. */
export default function Page({ data }: PageProps<'account/confirm-email'>) {
  if (data.token === '') {
    return (
      <AuthCard
        title="This link can't be used"
        lead="It has expired, has already been used, or was replaced by a newer one."
      >
        <Link href="/account">Ask again from your account</Link>
      </AuthCard>
    );
  }

  return (
    <AuthCard
      title="Move your account"
      lead={`From ${data.email ?? ''} to ${data.newEmail ?? ''}. You will sign in with the new address from now on.`}
    >
      <ConfirmEmailForm token={data.token} />
    </AuthCard>
  );
}
