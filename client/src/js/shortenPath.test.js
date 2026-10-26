import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import shortenPath from './shortenPath.js'

describe('shortenPath', () => {
  it('Keeps short paths as is', () => {
    assert.equal(shortenPath('/about-us/'), '/about-us/')
    assert.equal(shortenPath('/services/consulting/'), '/services/consulting/')
  })

  it('Shortens long paths to their start and end', () => {
    assert.equal(
      shortenPath('/services/consulting-and-advisory-for-small-business/getting-started-guide/'),
      '/services/.../getting-started-guide/',
    )
  })

  it('Keeps as many leading segments as fit', () => {
    assert.equal(
      shortenPath('/a/b/c/d/e/f/g/h/i/j/k/l/m/n/o/p/q/r/s/t/u/v/w/x/y/z/'),
      '/a/b/c/d/e/f/g/h/i/j/k/l/m/n/o/p/q/r/s/.../z/',
    )
  })

  it('Keeps long paths that cannot be shortened', () => {
    const path = '/services/consulting-and-advisory-for-small-business/'
    assert.equal(shortenPath(path), path)
  })

  it('Keeps the trailing slash as per the path', () => {
    assert.equal(
      shortenPath('/services/consulting-and-advisory-for-small-business/getting-started'),
      '/services/.../getting-started',
    )
  })
})
