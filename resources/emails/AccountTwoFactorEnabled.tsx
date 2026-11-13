import { Button, Divider, Heading, Layout, Text } from '@tinvar/mail';
import type { AccountMailData } from '../types/generated';

/**
 * That two-factor sign-in was turned on, and every other device signed out
 * (ADR 0215): from now on a code is asked at every sign-in, so the owner
 * hears of it -- someone else turning it on would keep them out.
 */
export default function AccountTwoFactorEnabled(props: AccountMailData) {
  return (
    <Layout preview={'Two-factor sign-in was turned on'} brand={props.appName} reason={props.reason}>
      <Heading>{'Two-factor sign-in was turned on'}</Heading>
      <Text>{`${props.name}, ${props.email} now asks for a code from an authenticator app whenever it signs in, and every other device was signed out.`}</Text>
      <Button href={props.url}>Reset your password</Button>
      <Divider />
      <Text muted>
        {
          "If you didn't do this, reset your password from the link above straight away: someone else's app would hold your codes."
        }
      </Text>
    </Layout>
  );
}
