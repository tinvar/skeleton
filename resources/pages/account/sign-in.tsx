import { Divider } from '@meridian/ui';
import { Link } from '@tinvar/react/link';
import type { PageProps } from '../../types/generated';
import { AuthCard } from '../../components/account/AuthCard';
import { PasskeySignIn, SignInForm } from '../../components/account/AccountForms';

/** Signing in, with a password or a passkey. */
export default function Page({ data }: PageProps<'account/sign-in'>) {
  return (
    <AuthCard
      title="Sign in"
      lead="Welcome back."
      below={
        <>
          <Link href="/sign-up">Make an account</Link>
          <Link href="/password/forgot">Forgot your password?</Link>
        </>
      }
    >
      <SignInForm next={data.next} />
      <Divider />
      <PasskeySignIn next={data.next} />
    </AuthCard>
  );
}
