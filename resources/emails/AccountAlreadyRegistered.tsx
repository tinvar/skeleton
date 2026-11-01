import { Button, Divider, Heading, Layout, Text } from '@tinvar/mail';
import type { AccountMailData } from '../types/generated';

/**
 * To an address someone signed up with again: no link that does anything,
 * only the pages to sign in or reset from.
 */
export default function AccountAlreadyRegistered(props: AccountMailData) {
  return (
    <Layout
      preview={`You already have a ${props.appName} account`}
      brand={props.appName}
      reason={props.reason}
    >
      <Heading>{'You already have an account'}</Heading>
      <Text>{`Someone asked to make an account for ${props.email}, and it already has one. If it was you, sign in -- or reset your password if you have forgotten it.`}</Text>
      <Button href={props.url}>Sign in</Button>
      <Button href={props.secondaryUrl}>Reset your password</Button>
      <Divider />
      <Text muted>{"If it wasn't you, ignore this email: nothing has changed."}</Text>
    </Layout>
  );
}
