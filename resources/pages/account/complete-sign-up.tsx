import { Banner } from '@meridian/ui';
import { Link } from '@tinvar/react/link';
import type { PageProps } from '../../types/generated';
import { AuthCard } from '../../components/account/AuthCard';
import { CompleteSignUpForm } from '../../components/account/AccountForms';

/**
 * Where a sign-up link lands. Opening it makes nothing; it says whose
 * account it makes -- so someone mailed a stranger's link knows to close
 * it -- and the form makes it.
 */
export default function Page({ data }: PageProps<'account/complete-sign-up'>) {
  if (data.token === '') {
    return (
      <AuthCard
        title="This link can't be used"
        lead="It has expired, has already been used, or was replaced by a newer one."
      >
        <Link href="/sign-up">Ask for a new link</Link>
      </AuthCard>
    );
  }

  return (
    <AuthCard title="Choose a password" lead={`This makes the account for ${data.email ?? ''}.`}>
      <Banner tone="info" title={`Hello, ${data.name ?? ''}`}>
        Not your address? Close this page: nothing is made until you choose a password.
      </Banner>
      <CompleteSignUpForm token={data.token} minLength={data.minLength} />
    </AuthCard>
  );
}
