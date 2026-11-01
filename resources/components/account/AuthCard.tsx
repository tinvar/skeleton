import type { ReactNode } from 'react';
import * as stylex from '@stylexjs/stylex';
import { Heading, Text } from '@meridian/ui';
import { colors, radius, shadows, space } from '@meridian/ui/tokens.stylex';
import { media } from '@meridian/ui/palette.stylex';
import { Title } from '@tinvar/react/head';

/** The centred card every signed-out account page is: signing up and in, and the pages a link lands on. */
export function AuthCard({
  title,
  lead,
  children,
  below,
}: {
  title: string;
  lead: ReactNode;
  children?: ReactNode;
  below?: ReactNode;
}) {
  return (
    <div {...stylex.props(styles.page)}>
      <Title>{title}</Title>
      <div {...stylex.props(styles.card)}>
        <div {...stylex.props(styles.heading)}>
          <Heading level={1} size="title2">
            {title}
          </Heading>
          <Text tone="secondary">{lead}</Text>
        </div>
        {children}
      </div>
      {below && <div {...stylex.props(styles.below)}>{below}</div>}
    </div>
  );
}

const styles = stylex.create({
  page: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    paddingBlock: { default: space.s8, [media.phone]: 0 },
    rowGap: space.s4,
  },
  card: {
    backgroundColor: colors.surface,
    borderRadius: radius.xl,
    boxShadow: shadows.md,
    display: 'flex',
    flexDirection: 'column',
    maxWidth: '400px',
    paddingBlock: space.s8,
    paddingInline: { default: space.s8, [media.phone]: space.s5 },
    rowGap: space.s6,
    width: '100%',
  },
  heading: { display: 'flex', flexDirection: 'column', rowGap: space.s2 },
  below: {
    columnGap: space.s4,
    display: 'flex',
    flexWrap: 'wrap',
    justifyContent: 'center',
    rowGap: space.s2,
  },
});
