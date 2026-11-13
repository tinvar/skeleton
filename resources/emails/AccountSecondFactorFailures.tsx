import { Button, Divider, Heading, Layout, Text } from '@tinvar/mail';
import type { AccountMailData } from '../types/generated';

/**
 * That a sign-in typed the account's password right, then too many wrong
 * two-factor codes, and was ended (ADR 0203): whoever it was has the
 * password, so the owner hears of it, with where to reset it from.
 */
export default function AccountSecondFactorFailures(props: AccountMailData) {
  return (
    <Layout preview={'A sign-in typed too many wrong codes'} brand={props.appName} reason={props.reason}>
      <Heading>{'A sign-in typed too many wrong codes'}</Heading>
      <Text>{`${props.name}, someone signed in to ${props.email} with its password, then typed too many wrong two-factor codes, so that sign-in was ended.`}</Text>
      <Button href={props.url}>Reset your password</Button>
      <Divider />
      <Text muted>
        {
          "If it wasn't you, someone knows your password: reset it from the link above straight away. If it was, there's nothing to do."
        }
      </Text>
    </Layout>
  );
}
