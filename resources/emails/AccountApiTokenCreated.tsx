import { Button, Divider, Heading, Layout, Text } from '@tinvar/mail';
import type { AccountMailData } from '../types/generated';

/**
 * That an API token was created on the account (ADR 0159). A token
 * outlives every session and every sign-in, so this is how the owner hears
 * of one they did not make, and where to revoke it from. It names the
 * token; its text is shown once, on the page that made it, and never here.
 */
export default function AccountApiTokenCreated(props: AccountMailData) {
  return (
    <Layout preview={'An API token was created on your account'} brand={props.appName} reason={props.reason}>
      <Heading>{'An API token was created'}</Heading>
      <Text>{`${props.name}, an API token called "${props.tokenName}" can now call the app's API as ${props.email}.`}</Text>
      <Button href={props.url}>Review your API tokens</Button>
      <Button href={props.secondaryUrl}>Reset your password</Button>
      <Divider />
      <Text muted>
        {
          "If you didn't make it, revoke it on your API tokens page, then reset your password straight away: that ends every token."
        }
      </Text>
    </Layout>
  );
}
