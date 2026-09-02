# Pool Infra YiiMP Changelog

This changelog records accepted Pool Infra downstream releases.

Unreleased work is not authoritative until it has completed acceptance and is
committed, tagged, and pushed to the Pool Infra GitHub repository.

## 74988f92.0003

Merged-mining and automatic coin-recovery release based on upstream lineage
74988f92.

### Accepted changes

- CHG-001: Automatic Recovery of Administratively Enabled Coins
- CHG-002: Generic AuxPoW Beneficiary Mapping
- CHG-003: Shared Algorithm Stratum Port in Miner UI

Related engineering decision:

- ADR-001: Separate Coin Health from Administrative Enablement

### Acceptance

Final runtime validation used:

    pool-infra-yiimp:0003-clean-candidate
    sha256:676e84fcfecd624e76350c9b732c9f01ccbf1708516876a41b94b6d4a32015c3

NENG-to-FLOOF validation demonstrated:

    15:56:13: scrypt: aux payout FLOOF -> userid 4
    15:56:23: *** ACCEPTED FloofyChain 173021 (+1)

Controlled NENG failure/recovery demonstrated:

    enable=1, auto_ready=1
        ->
    enable=1, auto_ready=0
        ->
    enable=1, auto_ready=1

without manual database repair or Stratum restart.

Rendered UI validation demonstrated that ABY and NENG advertise shared Scrypt
port `9000`.

Detailed behavior, acceptance evidence, and upstream disposition are recorded
in CHG-001 through CHG-003 and ADR-001.

Accepted implementation release:

    74988f92.0003

## 74988f92.0002

Accepted Pool Infra runtime reliability release based on upstream lineage
74988f92.

Tracked downstream source changes:

- `blocknotify/blocknotify.cpp`
- `config/supervisord.conf`
- `sql/2026-05-22-add_queue_table.sql`
- `yiimp2/config/console.php`
- `yiimp2/jobs/earnings/PaymentsJob.php`

Accepted commit:

    32e909c0

Detailed historical change records for this release are documentation debt and
must be reconstructed from the accepted source/history without inventing
unsupported rationale or acceptance evidence.

## 74988f92.0001

Frozen Pool Infra baseline derived from upstream commit:

    74988f929b65fb088d97444d718ecf869f5149d2

Pool Infra baseline commit:

    444ed33987907803008b5925653d845285fa2216

The downstream baseline added the Pool Infra `VERSION` file containing:

    74988f92.0001

This release is retained as an immutable rollback/reference point.
