import { Button, Divider, Heading, Layout, Text } from '@tinvar/mail';
import type { AccountMailData } from '../types/generated';

/**
 * That a passkey was added to the account. A passkey outlives every
 * session -- signing out everywhere does not remove it -- so this is how
 * the owner hears of one they did not add, and where to remove it from.
 */
export default function AccountPasskeyAdded(props: AccountMailData) {
  return (
    <Layout preview={'A passkey was added to your account'} brand={props.appName} reason={props.reason}>
      <Heading>{'A passkey was added'}</Heading>
      <Text>{`${props.name}, a passkey called "${props.passkey}" can now sign in to ${props.email}.`}</Text>
      <Button href={props.url}>Review your passkeys</Button>
      <Button href={props.secondaryUrl}>Reset your password</Button>
      <Divider />
      <Text muted>
        {"If you didn't add it, remove it on your account page, then reset your password straight away."}
      </Text>
    </Layout>
  );
}
