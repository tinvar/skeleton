import { Button, Divider, Heading, Layout, Text } from '@tinvar/mail';
import type { AccountMailData } from '../types/generated';

/**
 * An account the app made for someone, with its link to choose a first
 * password (ADR 0189). `minutes` carries the link's lifetime.
 */
export default function AccountWelcome(props: AccountMailData) {
  const days = Math.max(1, Math.round(props.minutes / 1440));

  return (
    <Layout preview={`Your ${props.appName} account is ready`} brand={props.appName} reason={props.reason}>
      <Heading>{'Your account is ready'}</Heading>
      <Text>{`${props.name}, an account was made for ${props.email}. Choose a password to sign in. The link works once, for ${days} days.`}</Text>
      <Button href={props.url}>Choose a password</Button>
      <Divider />
      <Text muted>
        {"If you weren't expecting this, ignore this email: nothing happens until a password is chosen."}
      </Text>
    </Layout>
  );
}
