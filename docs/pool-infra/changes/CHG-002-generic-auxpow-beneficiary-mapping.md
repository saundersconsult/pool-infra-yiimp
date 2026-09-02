# CHG-002: Generic AuxPoW Beneficiary Mapping

## Status

Accepted and runtime validated.

## Release

74988f92.0003

## What changed

Pool Infra adds generic per-miner AuxPoW payout-beneficiary resolution in:

- `stratum/client.cpp`
- `stratum/client.h`
- `stratum/client_submit.cpp`
- `stratum/db.h`
- `stratum/user.cpp`

A miner may provide an Aux-chain payout directive in its Stratum password:

    SYMBOL=<address>

For example:

    FLOOF=<FloofyChain-address>

The implementation resolves the symbol dynamically against currently known
coinds. It does not hardcode FLOOF, NENG, or another coin identity into the
generic merged-mining engine.

Each recognized Aux directive is validated against the corresponding child
daemon and resolved to the appropriate YiiMP account.

The resulting child coin/account mapping is stored per connected Stratum
client.

When a qualifying Aux block is accepted, the child earning is attributed to
the resolved child beneficiary rather than automatically inheriting the
parent-chain account.

## Why

A miner's valid payout address on the parent chain is not necessarily a valid
address on an AuxPoW child chain.

Using the parent account identity for every child therefore cannot provide
generic native child-chain payouts.

Pool Infra requires merged mining to support ordinary native payout semantics:

- parent rewards belong to the parent payout account;
- each explicitly mapped Aux child reward belongs to that child's payout
  account;
- the generic engine must not require hardcoded coin identities.

This allows compatible AuxPoW children to be added through ordinary
coin/daemon configuration rather than topology-specific payout code.

## How it behaves

Aux beneficiary processing is independent of global autoexchange mode.

For each recognized:

    SYMBOL=<address>

directive, Stratum:

1. searches currently known coinds dynamically;
2. requires the target to be an enabled/mineable Aux coin;
3. requires algorithm compatibility with the active Stratum;
4. validates the supplied address using the target child daemon;
5. rejects duplicate mappings for the same child;
6. resolves or creates the ordinary YiiMP account for that native child
   address;
7. stores the child coin ID to child user ID mapping on the Stratum client.

The implementation supports up to 16 Aux beneficiary mappings per client.

At Aux block submission/accounting time, the resolved child user ID is used
when one exists.

The parent account remains unchanged.

If no explicit mapping exists for an Aux child, existing fallback behavior is
preserved.

No ticker-specific parent/child topology is embedded in the generic resolver.

## Acceptance evidence

### NENG parent / FLOOF child

The accepted runtime test used:

Parent:

    NENG

AuxPoW child:

    FLOOF

Stratum endpoint:

    stratum+tcp://192.168.4.233:9000

Parent username:

    NMKjKXHfu2F6yXxKZPFbYHKH2HukEeYh25.NENG-L2-01

Aux directive:

    FLOOF=FGTPKThomJVuGxSMffgiHdWZ41tCvGrT7A

A second L2 worker used the same child payout directive.

Stratum resolved the child beneficiary as:

    scrypt: aux payout FLOOF -> userid 4

The corresponding YiiMP account was the native FLOOF account for:

    FGTPKThomJVuGxSMffgiHdWZ41tCvGrT7A

### Child block attribution and payout

A qualifying FLOOF AuxPoW block was accepted and its child earning was
attributed to the resolved child account.

The earning subsequently passed through normal maturity and clearing.

Native FLOOF payouts to the child beneficiary were demonstrated.

One explicitly verified payout was:

    amount: 39000 FLOOF
    txid: 69b5f80571182c82781b5654935c0ea9eb2fb33391da4fc2b8675fa5d22e6a64

Wallet RPC verification established that the payout transaction included the
configured native FLOOF beneficiary:

    FGTPKThomJVuGxSMffgiHdWZ41tCvGrT7A

Subsequent pool state showed sustained native FLOOF payouts rather than a
single isolated accounting event.

PASS: beneficiary resolution, child attribution, maturity, clearing, and
native payout were demonstrated end-to-end.

### Clean release-candidate validation

The Aux path was revalidated after removal of temporary ABY hybrid-parent
experiments and diagnostic instrumentation.

Candidate image:

    pool-infra-yiimp:0003-clean-candidate
    sha256:676e84fcfecd624e76350c9b732c9f01ccbf1708516876a41b94b6d4a32015c3

Database state after candidate deployment:

    FLOOF enable=1 auto_ready=1 connections=5 auxpow=1
    NENG  enable=1 auto_ready=1 connections=5 auxpow=0

The clean Stratum process rediscovered both daemons:

    15:56:07: connecting to coind FLOOF
    15:56:07: connecting to coind NENG

Three connected clients independently resolved:

    15:56:13: scrypt: aux payout FLOOF -> userid 4

Fresh NENG jobs were generated.

The candidate then recorded:

    15:56:23: *** ACCEPTED FloofyChain 173021 (+1)

PASS: the cleaned release candidate retains daemon discovery, generic child
beneficiary resolution, parent work generation, qualifying Aux submission,
and child-chain acceptance without the removed diagnostic/hybrid-parent
changes.

## Compatibility boundary discovered during testing

ABY was also investigated as a potential parent for FLOOF.

That combination was rejected as a standard-compatible topology because ABY
uses a transaction serialization containing an `nTime` field:

    nVersion | nTime | vin | vout | nLockTime

while FLOOF's AuxPoW parser expects the conventional transaction form:

    nVersion | vin | vout | nLockTime

FLOOF rejected the resulting Aux submission.

Removing or rewriting the parent transaction's `nTime` after hashing would
invalidate the parent transaction hash/merkle relationship.

Pool Infra therefore does not ship the temporary ABY hybrid-parent
experiments.

This is a parent/child wire-compatibility limitation, not a reason to hardcode
coin identities into the generic beneficiary implementation.

## Acceptance result

Generic single-Aux beneficiary mapping for a compatible Scrypt parent/child
pair is accepted.

PASS.

Multi-Aux runtime validation remains a separate future acceptance dimension
and is not claimed by this release evidence.

## Upstream disposition

Required downstream functionality against the currently accepted upstream
lineage.

For future upstream imports, inspect whether upstream provides equivalent:

- per-client native Aux beneficiary specification;
- child-address validation;
- child account resolution;
- child earning attribution;
- native child payout compatibility;
- arbitrary compatible Aux-coin handling without ticker-specific topology.

Retire this downstream implementation if upstream provides equivalent
semantics. Adapt it if upstream only partially provides them.

## Implementation references

Modified release files:

- `stratum/client.cpp`
  - invokes Aux beneficiary resolution during client authorization
- `stratum/client.h`
  - stores per-client Aux account mappings
- `stratum/client_submit.cpp`
  - attributes accepted Aux work to the mapped child user
- `stratum/db.h`
  - exposes the Aux account resolver
- `stratum/user.cpp`
  - parses, validates, resolves, and stores generic Aux beneficiary mappings

Accepted implementation release:

    74988f92.0003
