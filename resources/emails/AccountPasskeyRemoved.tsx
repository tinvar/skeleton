import { Button, Divider, Heading, Layout, Text } from '@tinvar/mail';
import type { AccountMailData } from '../types/generated';

/**
 * That a passkey was removed from the account: a way in taken away is
 * told as one added is, so the owner hears of a removal they did not make.
 */
export default function AccountPasskeyRemoved(props: AccountMailData) {
  return (
    <Layout preview={'A passkey was removed from your account'} brand={props.appName} reason={props.reason}>
      <Heading>{'A passkey was removed'}</Heading>
      <Text>{`${props.name}, the passkey called "${props.passkey}" no longer signs in to ${props.email}.`}</Text>
      <Button href={props.url}>Review your passkeys</Button>
      <Button href={props.secondaryUrl}>Reset your password</Button>
      <Divider />
      <Text muted>
        {
          "If you didn't remove it, reset your password straight away, then add your passkey again on your account page."
        }
      </Text>
    </Layout>
  );
}
