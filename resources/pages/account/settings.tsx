import type { ReactNode } from 'react';
import * as stylex from '@stylexjs/stylex';
import { Banner, Card, Heading, Text } from '@meridian/ui';
import { space } from '@meridian/ui/tokens.stylex';
import { Title } from '@tinvar/react/head';
import type { PageProps } from '../../types/generated';
import { ChangeEmailForm, ChangePasswordForm } from '../../components/account/AccountForms';
import { Passkeys, SignOutButton, TwoFactor } from '../../components/account/AccountSettings';

/** The account page: address, password, passkeys, two-factor and signing out everywhere. */
export default function Page({ data }: PageProps<'account/settings'>) {
  return (
    <div {...stylex.props(styles.page)}>
      <Title>Your account</Title>
      <header {...stylex.props(styles.header)}>
        <Heading level={1} size="title1">
          Your account
        </Heading>
        <Text tone="secondary">{`Signed in as ${data.name}, ${data.email}.`}</Text>
      </header>
      {data.notice && (
        <Banner tone="good" title="Done">
          {data.notice}
        </Banner>
      )}
      <Section title="Passkeys" lead="Sign in without a password.">
        <Passkeys passkeys={data.passkeys} />
      </Section>
      <Section title="Two-factor sign-in" lead="A code from an app as well as your password.">
        <TwoFactor
          enabled={data.twoFactorEnabled}
          setup={data.twoFactorSetup}
          recoveryCodesLeft={data.recoveryCodesLeft}
        />
      </Section>
      <Section title="Password" lead="Changing it signs out every other device.">
        <ChangePasswordForm minLength={data.minLength} />
      </Section>
      <Section
        title="Email address"
        lead="We send a link to the new address; nothing changes until it is opened."
      >
        <ChangeEmailForm />
      </Section>
      <Section title="Sessions" lead="Signed in somewhere you don't recognise? End every session at once.">
        <SignOutButton everywhere />
      </Section>
    </div>
  );
}

/** One settings section: a flat card with its heading row, as Meridian lays settings out. */
function Section({ title, lead, children }: { title: string; lead: string; children: ReactNode }) {
  return (
    <Card variant="flat" title={title} description={lead}>
      {children}
    </Card>
  );
}

const styles = stylex.create({
  page: { display: 'flex', flexDirection: 'column', maxWidth: '640px', rowGap: space.s6 },
  header: { display: 'flex', flexDirection: 'column', rowGap: space.s2 },
});
