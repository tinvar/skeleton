import { Button, Divider, Heading, Layout, Text } from '@tinvar/mail';
import type { AccountMailData } from '../types/generated';

/**
 * To the address an account moved away from.
 */
export default function AccountEmailChanged(props: AccountMailData) {
  return (
    <Layout preview={'Your address was changed'} brand={props.appName} reason={props.reason}>
      <Heading>{'Your address was changed'}</Heading>
      <Text>{`${props.name}, your ${props.appName} account now uses ${props.email}.`}</Text>
      <Button href={props.url}>Reset your password</Button>
      <Divider />
      <Text muted>{"If you didn't do this, reset your password from the link above straight away."}</Text>
    </Layout>
  );
}
