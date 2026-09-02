# ADR-001: Separate Coin Health from Administrative Enablement

## Status

Accepted and runtime validated.

## Context

Pool Infra observed that a temporary Nengcoin daemon restart caused YiiMP to
stop mining NENG and fail to recover automatically after the daemon became
healthy again.

During the restart, NENG RPC became temporarily reachable while
`getblocktemplate` was still unavailable.

Upstream behavior could persist operational failure into states that prevented
automatic rediscovery. Manual restoration of the coin's enabled/ready state
caused the already-running Stratum process to rediscover NENG and resume fresh
jobs, demonstrating that operator intent and transient operational health were
being conflated.

## Evidence

### Stratum failure behavior

`stratum/coind.cpp`:

    coind_error()

sets:

    coind->auto_ready = false;

and calls:

    object_delete(coind);

The existing YiiMP lifecycle therefore deliberately removes failed coind
objects rather than retaining them indefinitely.

### Persistence and discovery

Upstream `stratum/db.cpp` persisted `auto_ready=false` for failed coinds and
subsequently discovered coins using:

    enable AND auto_ready

Once `auto_ready=0` was persisted, an otherwise administratively enabled coin
could disappear from the normal Stratum discovery population indefinitely.

### PHP health behavior

The Yii1 coin backend and Yii2 CoinService could also convert daemon-health
failures into administrative disablement by changing `enable`.

That behavior made a temporary RPC/daemon problem indistinguishable from an
operator decision to remove the coin from service.

### Mining readiness

`rpc_connect()` establishes connectivity but does not prove that the daemon can
provide mining work.

A daemon may accept RPC while still starting, synchronizing, or loading its
block index.

`coind_create_job()` provides the authoritative mining-readiness boundary
because usable work requires successful RPC/template acquisition and job
creation.

### Recovery scheduling

No suitable persistent per-coin retry timestamp or generic existing coind
recovery scheduler was identified.

`db_update_coinds()` runs repeatedly from the Stratum main loop, so simply
including every `enable=1, auto_ready=0` coin in every reconciliation would
produce an undesirable tight retry/recreate loop.

Pool Infra therefore uses a bounded global recovery-probe cadence with an
initial interval of 300 seconds.

The interval is a minimum/bounded retry cadence, not a failure grace period and
not an exact wall-clock scheduler. Operational mining failure is recorded
promptly; recovery attempts continue while administrative enablement remains
true.

## Alternatives considered

### Disable the coin after a short RPC failure

Rejected.

A daemon restart or temporary RPC outage is an operational condition, not
evidence that the operator intends to remove the coin from service.

### Delay administrative disablement with a grace period

Rejected as the fundamental solution.

A grace period merely postpones the same incorrect terminal state and risks
confusing stale-work handling with administrative policy.

### Retain and resurrect failed coind objects

Rejected.

YiiMP already provides a prune/delete lifecycle for failed coind objects.
Resurrecting those objects would introduce a competing lifecycle.

### Prune and recreate enabled unhealthy coins

Selected.

This preserves the existing YiiMP lifecycle while allowing administratively
enabled unhealthy coins to be periodically recreated and authoritatively
retested.

## Decision

Pool Infra assigns distinct semantics to:

    enable     = persistent operator/administrative intent
    auto_ready = current operational mining readiness

Transient daemon, RPC, synchronization, or block-template failures MUST NOT
change `enable` from true to false.

A mining-health failure may immediately set:

    auto_ready=0

and withdraw current mining work.

An administratively enabled coin with `auto_ready=0` remains eligible for
bounded periodic recovery probing.

Recovery follows the existing prune/recreate lifecycle.

`auto_ready` may return to 1 only after authoritative mining readiness has been
demonstrated through successful work/template acquisition.

Successful recovery must create fresh work and resume mining without manual
database intervention or a Stratum restart.

An explicit operator setting:

    enable=0

remains authoritative and MUST NOT be automatically reversed by health
polling or recovery.

## Runtime validation

The design was first validated on the development recovery image and then
revalidated against release candidate image:

    pool-infra-yiimp:0003-clean-candidate
    sha256:676e84fcfecd624e76350c9b732c9f01ccbf1708516876a41b94b6d4a32015c3

On 2026-09-02 the controlled NENG test established:

Before failure:

    enable=1
    auto_ready=1
    connections=5

NENG was deliberately stopped at:

    2026-09-02 09:00:55 PDT

After failure detection:

    enable=1
    auto_ready=0
    connections=0

The same `stratum-scrypt` process remained RUNNING.

NENG was restarted at:

    2026-09-02 09:05:41 PDT

Ten seconds after restart, NENG RPC correctly reported:

    error code: -28
    Loading block index...

and YiiMP correctly retained:

    enable=1
    auto_ready=0
    connections=0

No database repair was performed.

Subsequent Stratum recovery activity included failed attempts while the daemon
was not mining-ready, followed by:

    16:12:01: connecting to coind NENG
    16:12:01: recovered NENG
    16:12:02: NENG 7644474 ... job a8 ...

Final database state was:

    enable=1
    auto_ready=1
    connections=5

`stratum-scrypt` remained continuously RUNNING with the same PID throughout
the controlled outage and recovery.

This demonstrates that connectivity alone does not restore readiness, failed
recovery attempts remain retryable, and successful authoritative recovery
restores fresh work without DB intervention or Stratum restart.

## Consequences

Temporary daemon failures promptly remove operational readiness without
destroying administrative intent.

Enabled unhealthy coins remain recovery eligible indefinitely.

Recovery attempts are bounded rather than executed on every reconciliation
cycle.

The 300-second interval must be documented as retry cadence rather than an
exact five-minute scheduling guarantee.

Future upstream imports must be checked for equivalent semantics before this
downstream behavior is removed.

## Upstream disposition

Required downstream behavior against the currently accepted upstream lineage.

For each upstream import, determine whether upstream now:

- preserves administrative `enable` across transient health failures;
- rediscovers enabled but not-ready coins;
- performs authoritative mining-readiness recovery;
- bounds retry behavior;
- resumes fresh work without manual intervention.

Retire this downstream decision only when upstream provides equivalent
semantics.
