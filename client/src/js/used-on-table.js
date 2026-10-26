/**
 * Replaces the "Used on" table (ie. the "Used on" tab of files) with one that also has a "Page URL" column,
 * linking to the page each record is on in the front end, to see the file in context.
 * The page comes from Normann\UsedOn\Forms\UsedOnTable, otherwise this renders as per the core component.
 * Uses the React and Injector globals provided by silverstripe/admin, so it's loaded in the CMS only.
 */
import shortenPath from './shortenPath.js'

const { React } = window
// The Injector global is the lib/Injector module, its default export being the injector
const Injector = window.Injector.default

const h = React.createElement

// eslint-disable-next-line no-underscore-dangle
const translate = (key, fallback) => (window.i18n ? window.i18n._t(key, fallback) : fallback)

// Long titles are truncated as per the core component
const truncate = (title) =>
  title && title.length >= 25 ? `${title.substring(0, 25).trim()}...` : title

function useUsageData(endpoint, tableRef) {
  const [isVisible, setIsVisible] = React.useState(false)
  const [state, setState] = React.useState({ loading: true, usedOn: null, error: null })
  const { method = 'GET', url } = endpoint || {}

  // Only load the usage data once the table is visible (ie. its tab is open), as per the core component
  React.useEffect(() => {
    const observer = new IntersectionObserver(([entry]) => {
      if (entry.isIntersecting) {
        setIsVisible(true)
      }
    })
    observer.observe(tableRef.current)

    return () => observer.disconnect()
  }, [])

  React.useEffect(() => {
    if (!isVisible) {
      return undefined
    }

    if (!url) {
      setState({
        loading: false,
        usedOn: null,
        error: translate(
          'Admin.NOT_AVAILABLE_USED_DATA',
          'The usage data is currently unavailable.',
        ),
      })
      return undefined
    }

    let isCancelled = false
    setState({ loading: true, usedOn: null, error: null })

    fetch(url, { method, headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`${response.status} ${response.statusText}`)
        }
        return response.json()
      })
      .then((data) => {
        if (!isCancelled) {
          setState({ loading: false, usedOn: data.usage, error: null })
        }
      })
      .catch((error) => {
        if (!isCancelled) {
          setState({ loading: false, usedOn: null, error: error.message })
        }
      })

    return () => {
      isCancelled = true
    }
  }, [isVisible, url, method])

  return state
}

// Links open in a new tab, so the CMS stays on the file
const newTabProps = { target: '_blank', rel: 'noopener noreferrer' }

function renderNewTabHint() {
  return [
    h(
      'span',
      { key: 'hint', className: 'visually-hidden' },
      ` (${translate('UsedOn.OPENS_IN_NEW_TAB', 'opens in a new tab')})`,
    ),
    h('span', {
      key: 'icon',
      className: 'used-on__link-icon font-icon-external-link',
      'aria-hidden': true,
    }),
  ]
}

function renderPageLink(page) {
  // Show the path only, the page's publishing status is shown as a badge
  const { pathname } = new URL(page.link, window.location.origin)
  const shortPath = shortenPath(pathname)
  const isShortened = shortPath !== pathname

  return h(
    'a',
    { href: page.link, title: pathname, className: 'used-on__page-link', ...newTabProps },
    // If shortened, show the full path to assistive technology instead
    h(
      'span',
      { className: 'used-on__page-path', 'aria-hidden': isShortened || undefined },
      shortPath,
    ),
    isShortened && h('span', { className: 'visually-hidden' }, pathname),
    ' ',
    h(
      'span',
      { className: `badge used-on__badge used-on__status used-on__status--${page.status}` },
      page.statusLabel,
    ),
    renderNewTabHint(),
  )
}

/**
 * Pages the record is on: usually one, or several for shared records (see SharedRecordExtension)
 */
function renderPages(pages) {
  if (pages.length === 1) {
    return renderPageLink(pages[0])
  }

  return h(
    'ul',
    { className: 'used-on__pages' },
    pages.map((page) => h('li', { key: page.link }, renderPageLink(page))),
  )
}

function renderRow(row, index, pageColumn) {
  // Show the record's ancestors first (ie. Page / Block / Download Item)
  const items = [row, ...(row.ancestors || [])].reverse()
  // Link to the record itself, or its closest ancestor with a link
  const cellLink = items.reduce((link, item) => item.link || link, '#')

  return h(
    'tr',
    { key: `${index}-${row.id}`, className: 'used-on__row' },
    h(
      'td',
      { className: 'used-on__col--index' },
      // Duplicates the link in the next cell, so it's hidden from assistive technology
      h(
        'a',
        {
          href: cellLink,
          className: 'used-on__cell-link',
          tabIndex: -1,
          'aria-hidden': true,
          ...newTabProps,
        },
        index + 1,
      ),
    ),
    h(
      'td',
      { className: 'used-on__col--title' },
      h(
        'a',
        { href: cellLink, className: 'used-on__cell-link', ...newTabProps },
        h(
          'ul',
          { className: 'used-on__title-items' },
          items.map((item, i) =>
            h(
              'li',
              {
                key: i,
                className: `used-on__title-item${i === 0 ? ' used-on__title-item--first' : ''}`,
              },
              truncate(item.title),
            ),
          ),
        ),
        h('span', { className: 'used-on__type' }, row.type, renderNewTabHint()),
      ),
    ),
    pageColumn &&
      h(
        'td',
        {
          // A single link is clickable from anywhere in its cell
          className: `used-on__col--page${row.pages?.length === 1 ? ' used-on__col--single-link' : ''}`,
        },
        row.pages?.length ? renderPages(row.pages) : null,
      ),
  )
}

function renderMessage({ loading, error, columns }) {
  let message = translate('Admin.NOT_USED', 'This file is currently not in use.')
  let classState = 'empty'

  if (error) {
    message = translate(
      'Admin.LOADING_ERROR',
      'As error occured when loading the data: {message}',
    ).replace('{message}', error)
    classState = 'error'
  } else if (loading) {
    message = h(Injector.component.get('Loading'))
    classState = 'loading'
  }

  return h(
    'tr',
    null,
    h('td', { className: `used-on__message used-on__message--${classState}`, colSpan: columns }, message),
  )
}

function UsedOnTable({ data }) {
  const tableRef = React.useRef(null)
  const { loading, usedOn, error } = useUsageData(data?.readUsageEndpoint, tableRef)
  const hasRows = !error && usedOn && usedOn.length > 0
  // See Normann\UsedOn\Forms\UsedOnTable config. The file editing pane is widened to fit the page column.
  const pageColumn = data?.pageColumn !== false

  return h(
    'table',
    {
      ref: tableRef,
      className: `table used-on__table${pageColumn ? ' used-on__table--page-column' : ''}`,
    },
    h(
      'thead',
      null,
      h(
        'tr',
        null,
        h(
          'th',
          { scope: 'col', className: 'used-on__col--index' },
          translate('Admin.USED_ON_NUM', '#'),
        ),
        h(
          'th',
          { scope: 'col', className: 'used-on__col--title' },
          pageColumn
            ? translate('UsedOn.CMS_EDITING', 'CMS Editing')
            : translate('Admin.USED_ON', 'Used on'),
        ),
        pageColumn &&
          h(
            'th',
            { scope: 'col', className: 'used-on__col--page' },
            translate('UsedOn.PAGE_URL', 'Page URL'),
          ),
      ),
    ),
    h(
      'tbody',
      { 'aria-live': 'polite' },
      hasRows
        ? usedOn.map((row, index) => renderRow(row, index, pageColumn))
        : renderMessage({ loading, error, columns: pageColumn ? 3 : 2 }),
    ),
  )
}

window.document.addEventListener('DOMContentLoaded', () => {
  Injector.transform('used-on-table', (updater) => {
    updater.component('UsedOnTable', () => UsedOnTable)
  })
})
