import { Button, Divider, Heading, Layout, Text } from '@tinvar/mail';
import type { AccountMailData } from '../types/generated';

/**
 * That two-factor sign-in was turned off: the password alone signs in to
 * the account now, and every other device was signed out. The one way the
 * owner hears of it if it was not them.
 */
export default function AccountTwoFactorDisabled(props: AccountMailData) {
  return (
    <Layout preview={'Two-factor sign-in was turned off'} brand={props.appName} reason={props.reason}>
      <Heading>{'Two-factor sign-in was turned off'}</Heading>
      <Text>{`${props.name}, ${props.email} no longer asks for a code from an authenticator app when it signs in, and every other device was signed out.`}</Text>
      <Button href={props.url}>Reset your password</Button>
      <Divider />
      <Text muted>
        {
          "If you didn't do this, reset your password from the link above straight away, then turn two-factor sign-in on again."
        }
      </Text>
    </Layout>
  );
}
