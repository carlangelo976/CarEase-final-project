
export async function postOtpApi(path, data) {
  // Get the CSRF token from the existing backend session.
  const sessionResponse = await fetch('/carease/api/session', {
    credentials: 'include'
  })

  if (!sessionResponse.ok) {
    throw new Error('Unable to connect to the server.')
  }

  const session = await sessionResponse.json()

  if (!session.csrf) {
    throw new Error('Missing CSRF token. Please refresh the page.')
  }

  // Send the registration or OTP request.
  const response = await fetch('/carease/api' + path, {
    method: 'POST',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: new URLSearchParams({
      ...data,
      csrf: session.csrf
    }).toString()
  })

  const raw = await response.text()
  let result

  try {
    result = JSON.parse(raw)
  } catch {
    throw new Error(
      'Invalid server response. Check the PHP API routes.'
    )
  }

  if (!response.ok) {
    throw new Error(result.error || 'Request failed.')
  }

  return result
}
