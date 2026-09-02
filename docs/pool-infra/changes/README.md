# Pool Infra Change Records

Create one record for every substantive downstream behavioral change.

Suggested filename:

    CHG-NNN-short-description.md

## Required template

# CHG-NNN: Title

## Status

Proposed | Testing | Accepted | Superseded | Retired

## Release

Pool Infra release containing the accepted change, or `Unreleased`.

## What changed

Identify exact source files, functions/components, and resulting behavior.

## Why

Describe the upstream limitation, defect, or Pool Infra requirement.

Record the evidence that established the need for the change.

## How it behaves

Describe the intended Pool Infra behavior.

Explicitly identify behavior that differs from upstream.

## Acceptance evidence

### PASS criteria

Define what must be demonstrated for acceptance.

### Test procedure

Record the procedure used to exercise the change.

### Observed results

Record the actual results. Do not substitute expected behavior for observed
behavior.

## Upstream disposition

State whether this downstream modification is still required.

When evaluating a new upstream release, record whether the upstream version:

- does not address the requirement;
- partially addresses it;
- fully supersedes the Pool Infra modification;
- requires the Pool Infra patch to be adapted;
- allows the Pool Infra patch to be retired.

## Implementation references

Record affected files/functions and accepted Git commit(s).

## Related decisions

Reference applicable ADR/decision records.

## Accepted change records

### 74988f92.0003

- `CHG-001-automatic-coin-recovery.md`
- `CHG-002-generic-auxpow-beneficiary-mapping.md`
- `CHG-003-shared-stratum-port-ui.md`
