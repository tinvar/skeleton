/**
 * The browser's half of a passkey ceremony: the options the server sent --
 * every buffer base64url, as JSON carries them -- turned into what
 * navigator.credentials wants, and the credential it answers with turned
 * back into JSON for the action to verify.
 *
 * The server does all the checking (Tinvar\Accounts\Passkeys\WebAuthn):
 * the challenge it issued, bound to this session; the origin; this host as
 * the relying party; that the user was verified; the signature and the
 * counter. Nothing here decides anything.
 */

type Json = Record<string, unknown>;

export function passkeysSupported(): boolean {
  return typeof window !== 'undefined' && typeof window.PublicKeyCredential === 'function';
}

function fromBase64Url(text: string): ArrayBuffer {
  const base64 = text.replace(/-/g, '+').replace(/_/g, '/');
  const binary = atob(base64 + '='.repeat((4 - (base64.length % 4)) % 4));
  const bytes = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
  return bytes.buffer;
}

function toBase64Url(buffer: ArrayBuffer): string {
  const bytes = new Uint8Array(buffer);
  let binary = '';
  for (const byte of bytes) binary += String.fromCharCode(byte);
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function descriptors(list: unknown): PublicKeyCredentialDescriptor[] {
  return (Array.isArray(list) ? list : []).map((entry: Json) => ({
    type: 'public-key',
    id: fromBase64Url(String(entry.id)),
    ...(Array.isArray(entry.transports) ? { transports: entry.transports as AuthenticatorTransport[] } : {}),
  }));
}

/** Makes a passkey from the account.passkeyOptions action's answer; the credential as JSON. */
export async function createPasskey(options: Json): Promise<string> {
  const user = options.user as Json;
  const credential = (await navigator.credentials.create({
    publicKey: {
      ...(options as unknown as PublicKeyCredentialCreationOptions),
      challenge: fromBase64Url(String(options.challenge)),
      user: {
        id: fromBase64Url(String(user.id)),
        name: String(user.name),
        displayName: String(user.displayName),
      },
      excludeCredentials: descriptors(options.excludeCredentials),
    },
  })) as PublicKeyCredential | null;
  if (!credential) throw new Error('No passkey was made.');
  const response = credential.response as AuthenticatorAttestationResponse;

  return JSON.stringify({
    id: credential.id,
    rawId: toBase64Url(credential.rawId),
    type: credential.type,
    response: {
      clientDataJSON: toBase64Url(response.clientDataJSON),
      attestationObject: toBase64Url(response.attestationObject),
      transports: typeof response.getTransports === 'function' ? response.getTransports() : [],
    },
  });
}

/** Signs the account.passkeySignInOptions action's challenge with a passkey; the answer as JSON. */
export async function signWithPasskey(options: Json): Promise<string> {
  const credential = (await navigator.credentials.get({
    publicKey: {
      challenge: fromBase64Url(String(options.challenge)),
      rpId: String(options.rpId),
      userVerification: 'required',
      timeout: Number(options.timeout),
      allowCredentials: descriptors(options.allowCredentials),
    },
  })) as PublicKeyCredential | null;
  if (!credential) throw new Error('No passkey answered.');
  const response = credential.response as AuthenticatorAssertionResponse;

  return JSON.stringify({
    id: credential.id,
    rawId: toBase64Url(credential.rawId),
    type: credential.type,
    response: {
      clientDataJSON: toBase64Url(response.clientDataJSON),
      authenticatorData: toBase64Url(response.authenticatorData),
      signature: toBase64Url(response.signature),
      ...(response.userHandle ? { userHandle: toBase64Url(response.userHandle) } : {}),
    },
  });
}
