# Task 015: `ProxyLocator` + `ProxyAutoloader` + preference-aware scanning

**Status**: completed
**Depends on**: 014
**Retry count**: 0

## Description
Build the runtime side of the codegen: a deterministic mapping from original config class FQN → generated proxy FQN (`ProxyLocator`), a Composer-compatible autoloader that resolves the generated FQNs from the target directory (`ProxyAutoloader`), and a preference-aware scanner that, given Marko's preference map + the registry, expands the set of classes that need proxies (so subclassed-via-Preference variants also get codegen'd).

## Context
- `ProxyLocator::proxyClassFor(string $originalClass): string` — deterministic, doesn't touch the filesystem; just transforms the FQN
- `ProxyAutoloader` registers itself via `spl_autoload_register(...)` and resolves `Markommerce\Config\Generated\...` class names to files under the target directory
- `PreferenceAwareScanner::expand(list<class-string> $registeredConfigClasses, PreferenceRegistry $preferenceRegistry): list<class-string>` returns every class that needs a proxy: each registered config class itself + every concrete class that `PreferenceRegistry::getPreference($original)` returns
- Marko's preference resolution: `Marko\Core\Container\PreferenceRegistry::getPreference(string $original): ?string` already follows chains and throws `PreferenceConflictException::circularPreference()` on cycles — the scanner reuses this directly and does NOT duplicate cycle detection
- Preferences in Marko are declared via the `#[Preference(replaces: OriginalClass::class)]` attribute on the *replacement class* (see `Marko\Core\Attributes\Preference`) and are discovered at app boot by `Marko\Core\Container\PreferenceDiscovery`. The scanner therefore needs the populated `PreferenceRegistry` from the Application — task 020 must register it in the container as an instance binding before this code runs
- The scanner MUST validate that each preferred class is a subclass of the original (`is_subclass_of($preferred, $original) === true`). If not, throw `InvalidConfigClassException::nonSubclassPreference($original, $preferred)` — `ConfigResolver::get(Original::class)` promises a `T = Original` return, which is only honored when the proxy's `extends` chain reaches `Original`
- If a preferred subclass adds new config properties not on the parent, those need proxies too — the scanner doesn't need to know that, but task 014's generator handles it because it reflects on the *resolved* class

## Requirements (Test Descriptions)
- [x] `it maps an original class FQN to a deterministic generated proxy FQN`
- [x] `it locates the same proxy FQN regardless of leading backslash variations in the original FQN`
- [x] `it autoloads a generated proxy class from a file under the target directory`
- [x] `it returns false from the autoloader when the requested class is not under the Generated namespace`
- [x] `it expands a list of registered classes by adding preference subclasses returned by PreferenceRegistry::getPreference`
- [x] `it follows nested preferences via PreferenceRegistry::getPreference (which already chains)`
- [x] `it throws InvalidConfigClassException when a preferred class is not a subclass of the original config class`
- [x] `it propagates PreferenceConflictException::circularPreference unchanged when PreferenceRegistry detects a cycle (no duplicate cycle detection in the scanner)`

## Acceptance Criteria
- `ProxyLocator` is stateless (no constructor deps)
- `ProxyAutoloader` accepts the target directory in its constructor and exposes a `register(): void` method
- `PreferenceAwareScanner` accepts a `Marko\Core\Container\PreferenceRegistry` in its constructor; `expand(list<class-string> $configClasses): list<class-string>` returns a deduplicated list
- Tests use a real `PreferenceRegistry` instance (it has no constructor deps) populated via `register(original, replacement, …)` — no need to mock
- PHPStan level 8 clean
- Tests use real file-system fixtures inside a temp directory (no mocks of `file_exists`/`require`)

## Implementation Notes
(Left blank — filled in by programmer)
