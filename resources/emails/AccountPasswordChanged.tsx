import { Button, Divider, Heading, Layout, Text } from '@tinvar/mail';
import type { AccountMailData } from '../types/generated';

/**
 * That the account's password was changed or reset: the one way the
 * owner hears of a change they did not make.
 */
export default function AccountPasswordChanged(props: AccountMailData) {
  return (
    <Layout preview={'Your password was changed'} brand={props.appName} reason={props.reason}>
      <Heading>{'Your password was changed'}</Heading>
      <Text>{`${props.name}, the password of ${props.email} was just changed, and every other device was signed out.`}</Text>
      <Button href={props.url}>Reset your password</Button>
      <Divider />
      <Text muted>{"If you didn't do this, reset your password from the link above straight away."}</Text>
    </Layout>
  );
}
