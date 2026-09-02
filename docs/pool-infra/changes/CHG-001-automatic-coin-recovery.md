# CHG-001: Automatic Recovery of Administratively Enabled Coins

## Status

Accepted and runtime validated.

## Release

74988f92.0003

## What changed

Pool Infra changes coin-health and recovery behavior in:

- `stratum/db.cpp`
- `web/yaamp/core/backend/coins.php`
- `yiimp2/services/CoinService.php`

`stratum/db.cpp` adds bounded recovery discovery for administratively enabled
coins whose `auto_ready` state is false.

The Yii1 and Yii2 health paths no longer change administrative `enable`
because of transient daemon/RPC health.

The implementation preserves:

    enable     = administrative intent
    auto_ready = operational mining readiness

## Why

Upstream behavior could convert a transient daemon outage into a persistent
state from which the coin did not automatically recover.

NENG originally demonstrated the defect: daemon startup temporarily prevented
valid mining work, YiiMP persisted unhealthy/disabled state, and the healthy
daemon was not subsequently rediscovered without manual database repair.

Pool Infra requires daemon health to be recoverable without overwriting an
operator's explicit enable/disable decision.

## How it behaves

Healthy enabled coin:

    enable=1
    auto_ready=1

Operationally unavailable enabled coin:

    enable=1
    auto_ready=0

Administratively disabled coin:

    enable=0

On daemon/RPC/template failure:

- current operational readiness may be removed immediately;
- `auto_ready` becomes false as appropriate;
- administrative `enable` remains unchanged;
- existing failed coind objects follow the normal YiiMP prune lifecycle.

Enabled/not-ready coins remain eligible for recovery probing.

Recovery probing is bounded by an initial 300-second minimum retry interval.
It is not an exact five-minute wall-clock scheduler.

During a recovery probe:

- a coind object is recreated through the existing lifecycle;
- RPC and mining work are tested;
- failure leaves the database state recovery eligible;
- success restores `auto_ready=1` only after authoritative mining work is
  available;
- fresh jobs resume without restarting Stratum.

The database update restoring readiness is conditional on the coin still being
administratively enabled. An operator disable occurring during recovery is
therefore not automatically reversed.

## Acceptance evidence

### Administrative-disable behavior

An explicit test set NENG:

    enable=0

while its daemon was healthy.

Automatic health processing did not restore `enable=1`.

PASS: explicit administrative disable remains authoritative.

### Initial development recovery validation

A controlled NENG outage demonstrated:

    enable=1, auto_ready=1
        ->
    enable=1, auto_ready=0

After the daemon returned, failed recovery attempts remained retryable.
A later successful probe produced:

    recovered NENG

followed by fresh NENG jobs and:

    enable=1
    auto_ready=1

No manual DB repair and no Stratum restart were required.

PASS.

### Clean release-candidate validation

The complete failure/recovery lifecycle was repeated against:

    pool-infra-yiimp:0003-clean-candidate
    sha256:676e84fcfecd624e76350c9b732c9f01ccbf1708516876a41b94b6d4a32015c3

Pre-failure database state:

    id=9
    symbol=NENG
    enable=1
    auto_ready=1
    connections=5

`stratum-scrypt` was RUNNING.

NENG was stopped at:

    2026-09-02 09:00:55 PDT

During the outage:

    enable=1
    auto_ready=0
    connections=0

PASS: operational failure did not overwrite administrative intent.

NENG was restarted at:

    2026-09-02 09:05:41 PDT

Ten seconds later the daemon reported:

    error code: -28
    Loading block index...

Database state correctly remained:

    enable=1
    auto_ready=0
    connections=0

PASS: RPC reachability/startup alone did not falsely restore mining readiness.

Observed recovery events included failed attempts followed by:

    16:12:01: connecting to coind NENG
    16:12:01: recovered NENG
    16:12:02: NENG 7644474 ... job a8 ...

Final database state:

    enable=1
    auto_ready=1
    connections=5

The same Stratum process remained continuously RUNNING.

PASS: autonomous authoritative recovery completed without DB intervention or
Stratum restart.

### Regression evidence

The clean candidate also successfully initialized NENG with FLOOF as an AuxPoW
child and subsequently recorded:

    15:56:13: scrypt: aux payout FLOOF -> userid 4
    15:56:23: *** ACCEPTED FloofyChain 173021 (+1)

PASS: the recovery changes do not prevent the accepted NENG-to-FLOOF merged
mining path.

## Acceptance result

All required recovery semantics have been demonstrated.

PASS.

## Upstream disposition

Required downstream change against the currently accepted upstream lineage.

For every future upstream import, inspect whether upstream now:

- preserves `enable` across transient health failures;
- rediscovers `enable=1, auto_ready=0` coins;
- provides equivalent authoritative recovery probing;
- bounds retry frequency;
- automatically resumes fresh jobs after daemon recovery.

Retire this Pool Infra change if upstream fully provides these semantics.
Adapt it if upstream only partially addresses them.

## Implementation references

Modified release files:

- `stratum/db.cpp`
  - bounded recovery probing in `db_update_coinds()`
  - conditional readiness restoration after successful job creation
- `web/yaamp/core/backend/coins.php`
  - health failure updates operational state without changing `enable`
- `yiimp2/services/CoinService.php`
  - equivalent separation of administrative enablement and health

Investigated upstream lifecycle components, intentionally not modified by this
release:

- `stratum/coind.cpp`
  - `coind_error()`
- `stratum/coind_template.cpp`
  - template/GBT failure paths
- `stratum/stratum.cpp`
  - object pruning
- `stratum/coind.h`
  - coind deletion lifecycle

Accepted implementation release:

    74988f92.0003

## Related decisions

- ADR-001: Separate Coin Health from Administrative Enablement
