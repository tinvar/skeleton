import { Button, Divider, Heading, Layout, Text } from '@tinvar/mail';
import type { AccountMailData } from '../types/generated';

/**
 * To the new address an account asked to move to: the link that moves it.
 */
export default function AccountEmailChange(props: AccountMailData) {
  return (
    <Layout preview={'Confirm your new address'} brand={props.appName} reason={props.reason}>
      <Heading>{'Confirm your new address'}</Heading>
      <Text>{`${props.name}, this link moves your ${props.appName} account to ${props.email}. It works once, for ${props.minutes} minutes.`}</Text>
      <Button href={props.url}>Move my account here</Button>
      <Divider />
      <Text muted>{"If you didn't ask, ignore this email: the account stays where it is."}</Text>
    </Layout>
  );
}
