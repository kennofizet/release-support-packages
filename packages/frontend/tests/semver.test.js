import { compareSemver, isOutdated, parseSemver } from '../src/utils/semver.js'

function assert(cond, msg) {
  if (!cond) throw new Error(msg)
}

assert(compareSemver('1.0.0', '1.0.1') === -1, 'patch less')
assert(compareSemver('2.0.0', '1.9.9') === 1, 'major greater')
assert(isOutdated('1.0.0', '1.1.0') === true, 'outdated')
assert(parseSemver('v1.2.3')?.[0] === 1, 'parse v prefix')
console.log('semver tests ok')
