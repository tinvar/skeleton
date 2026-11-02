import { Button, Divider, Heading, Layout, Text } from '@tinvar/mail';
import type { AccountMailData } from '../types/generated';

/**
 * The link that makes an account (ADR 0152). Opening it makes nothing --
 * mail scanners open links too -- so the page it opens asks for a password
 * first.
 */
export default function AccountSignUp(props: AccountMailData) {
  return (
    <Layout preview={`Finish signing up to ${props.appName}`} brand={props.appName} reason={props.reason}>
      <Heading>{`Finish signing up to ${props.appName}`}</Heading>
      <Text>{`${props.name}, this link makes your account for ${props.email}. It works once, for ${Math.round(props.minutes / 60)} hours.`}</Text>
      <Button href={props.url}>Choose a password</Button>
      <Divider />
      <Text muted>
        {"If you didn't ask for an account, ignore this email: nothing is made until the link is used."}
      </Text>
    </Layout>
  );
}
