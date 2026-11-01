import { Link } from '@tinvar/react/link';
import type { PageProps } from '../../types/generated';
import { AuthCard } from '../../components/account/AuthCard';
import { SecondFactorForm } from '../../components/account/AccountForms';

/** The code a sign-in waits on, where two-factor sign-in is on. */
export default function Page({ data }: PageProps<'account/two-factor'>) {
  if (!data.waiting) {
    return (
      <AuthCard
        title="Sign in again"
        lead="Your sign-in waited too long for its code, or was never started here."
      >
        <Link href="/sign-in">Go to sign in</Link>
      </AuthCard>
    );
  }

  return (
    <AuthCard
      title="Two-factor sign-in"
      lead="Type the code from your authenticator app to finish signing in."
    >
      <SecondFactorForm next={data.next} />
    </AuthCard>
  );
}
