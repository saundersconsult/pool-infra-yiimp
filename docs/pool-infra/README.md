# Pool Infra YiiMP Documentation

This directory documents the Pool Infra downstream derivative of YiiMP.

GitHub is the authoritative repository for accepted Pool Infra source releases.
A Pool Infra release is not considered complete until its source changes and
required documentation have passed acceptance and are committed, tagged, and
pushed together.

## Documentation hierarchy

### CHANGELOG.md

Release-notes-level description of what shipped in each accepted Pool Infra
release.

### changes/

One change record for every substantive downstream behavioral modification.

Every change record MUST document:

1. What changed
   - affected files, functions, components, and resulting behavior.

2. Why
   - upstream limitation, defect, or requirement;
   - evidence establishing the need for the modification.

3. How it behaves
   - intended Pool Infra semantics;
   - differences from upstream behavior.

4. Acceptance evidence
   - test procedure;
   - observed result;
   - explicit PASS criteria.

5. Upstream disposition
   - whether the downstream patch remains required;
   - whether a future upstream implementation supersedes it;
   - whether it must be adapted or can be retired.

### decisions/

Engineering decision records preserve the reasoning behind significant design
choices, including alternatives considered or tested, evidence, tradeoffs, and
the selected approach.

## Traceability

Release notes should reference the corresponding change record.

Change records should reference:

- relevant decision records;
- affected source files/functions;
- implementation commit(s), once accepted;
- acceptance evidence;
- upstream disposition.

Decision records should reference related change records where applicable.

## Release gate

No Pool Infra release tag is promoted to authoritative known-good status unless:

- release notes are complete;
- every substantive downstream behavioral change has a change record;
- significant engineering decisions are documented;
- acceptance evidence is recorded;
- temporary debug/acceptance artifacts have been removed;
- the release source and documentation are committed and tagged together.
