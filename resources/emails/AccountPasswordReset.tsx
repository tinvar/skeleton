import { Button, Divider, Heading, Layout, Text } from '@tinvar/mail';
import type { AccountMailData } from '../types/generated';

/**
 * A password reset's link (ADR 0076).
 */
export default function AccountPasswordReset(props: AccountMailData) {
  return (
    <Layout preview={`Reset your ${props.appName} password`} brand={props.appName} reason={props.reason}>
      <Heading>{'Reset your password'}</Heading>
      <Text>{`${props.name}, this link lets you choose a new password for ${props.email}. It works once, for ${props.minutes} minutes.`}</Text>
      <Button href={props.url}>Choose a new password</Button>
      <Divider />
      <Text muted>{"If you didn't ask, ignore this email: your password stays as it is."}</Text>
    </Layout>
  );
}
