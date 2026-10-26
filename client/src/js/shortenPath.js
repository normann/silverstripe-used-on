const maxPathLength = 45

/**
 * Shorten long paths to their start and end, ie. /services/.../getting-started-guide/
 * Keeps as many leading URL segments as fit, and always the last one.
 */
export default function shortenPath(path) {
  if (path.length <= maxPathLength) {
    return path
  }

  const segments = path.split('/').filter(Boolean)
  if (segments.length < 3) {
    return path
  }

  const end = `/.../${segments[segments.length - 1]}${path.endsWith('/') ? '/' : ''}`
  let start = `/${segments[0]}`
  for (let i = 1; i < segments.length - 1; i += 1) {
    const next = `${start}/${segments[i]}`
    if (`${next}${end}`.length > maxPathLength) {
      break
    }
    start = next
  }

  return `${start}${end}`
}
