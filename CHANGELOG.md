# Pool Infra YiiMP Changelog

This changelog records accepted Pool Infra downstream releases.

Unreleased work is not authoritative until it has completed acceptance and is
committed, tagged, and pushed to the Pool Infra GitHub repository.

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
