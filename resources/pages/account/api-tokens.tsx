import * as stylex from '@stylexjs/stylex';
import {
  Badge,
  Card,
  EmptyState,
  Heading,
  RelativeTime,
  Table,
  TBody,
  Td,
  Text,
  Th,
  THead,
  Tr,
} from '@meridian/ui';
import { space } from '@meridian/ui/tokens.stylex';
import { Title } from '@tinvar/react/head';
import { Link } from '@tinvar/react/link';
import type { PageProps } from '../../types/generated';
import { CreateApiTokenForm, RevokeApiTokenButton } from '../../components/account/ApiTokenForms';
import { ConfirmProvider } from '../../components/account/ConfirmIdentity';

/**
 * The account's API tokens (ADR 0159): what a script of the user's signs in
 * with to call the app's API. Each is listed by its name with where it
 * stands, never its text, which only the answer to creating it carries.
 * Signing out everywhere ends every one of them; signing in elsewhere does not.
 * Making one asks "Confirm it's you" first, as adding a passkey does.
 */
export default function Page({ data }: PageProps<'account/api-tokens'>) {
  return (
    <div {...stylex.props(styles.page)}>
      <Title>API tokens</Title>
      <header {...stylex.props(styles.header)}>
        <Text size="sm" tone="secondary">
          <Link href="/account">Your account</Link>
        </Text>
        <Heading level={1} size="title1">
          API tokens
        </Heading>
        <Text tone="secondary">
          {`Let a script of yours call the app's API as you. You may hold ${data.maxTokens} at once; signing out everywhere, a new password or address, or turning two-factor off ends them all.`}
        </Text>
      </header>

      <Card variant="flat" title="New token" description="Choose only the scopes the script needs.">
        <ConfirmProvider
          confirmedUntil={data.confirmedUntil}
          hasPasskeys={data.hasPasskeys}
          twoFactorEnabled={data.twoFactorEnabled}
        >
          <CreateApiTokenForm scopes={data.scopes} lifetimes={data.lifetimes} />
        </ConfirmProvider>
      </Card>

      {data.tokens.length === 0 ? (
        <EmptyState
          title="No tokens yet"
          description="Tokens you create are listed here by name, with when each was last used."
        />
      ) : (
        <Table label="Your API tokens">
          <THead>
            <Tr>
              <Th pinned>Name</Th>
              <Th>Scopes</Th>
              <Th>Status</Th>
              <Th numeric>Last used</Th>
              <Th numeric>Expires</Th>
              <Th>
                <span {...stylex.props(styles.hidden)}>Actions</span>
              </Th>
            </Tr>
          </THead>
          <TBody>
            {data.tokens.map((token) => (
              <Tr key={token.id}>
                <Td pinned>{token.name}</Td>
                <Td>{token.scopes.join(', ')}</Td>
                <Td>
                  <Badge tone={token.status === 'active' ? 'good' : 'neutral'} title={token.statusLabel}>
                    {token.status === 'active' ? 'Active' : token.statusLabel}
                  </Badge>
                </Td>
                <Td numeric>{token.lastUsedAt ? <RelativeTime date={token.lastUsedAt} /> : 'Never'}</Td>
                <Td numeric>{token.expiresAt ? <RelativeTime date={token.expiresAt} /> : 'Never'}</Td>
                <Td>
                  {token.status === 'active' && <RevokeApiTokenButton id={token.id} name={token.name} />}
                </Td>
              </Tr>
            ))}
          </TBody>
        </Table>
      )}
    </div>
  );
}

const styles = stylex.create({
  page: { display: 'flex', flexDirection: 'column', maxWidth: '760px', rowGap: space.s6 },
  header: { display: 'flex', flexDirection: 'column', rowGap: space.s2 },
  hidden: {
    blockSize: 1,
    clipPath: 'inset(50%)',
    inlineSize: 1,
    overflow: 'hidden',
    position: 'absolute',
    whiteSpace: 'nowrap',
  },
});
