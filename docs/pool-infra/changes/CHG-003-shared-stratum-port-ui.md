# CHG-003: Shared Algorithm Stratum Port in Miner UI

## Status

Accepted and runtime validated.

## Release

74988f92.0003

## What changed

Pool Infra changes:

- `web/yaamp/modules/site/index.php`

The miner connection UI now obtains the Stratum port from the algorithm-level
listener configuration rather than attempting to resolve a runtime Stratum
entry by both algorithm and coin symbol.

The Pool Infra implementation uses:

    getAlgoPort($algo)

for the displayed miner connection port.

## Why

Pool Infra deliberately uses shared Stratum listeners by algorithm.

For the current Scrypt pool:

    192.168.4.233:9000

is the shared Scrypt listener.

The miner selects the parent coin through the normal YiiMP connection
parameters rather than connecting to a unique TCP port for each coin.

The previous UI lookup attempted to associate the listener with both an
algorithm and a coin symbol.

The runtime shared-listener row has no per-coin symbol, so that lookup failed
and the generated coin-selection data could advertise port:

    0000

This was a UI representation error. It did not describe the actual Pool Infra
Stratum topology.

## How it behaves

All coins served by the same configured algorithm listener advertise that
algorithm's shared Stratum port.

For the accepted Scrypt configuration:

    ABY  -> 9000
    NENG -> 9000

The implementation does not introduce per-coin Stratum listeners.

The initial static HTML placeholder may still contain:

    :0000

before JavaScript applies the selected coin's generated connection data.

That placeholder is not treated as a runtime port assignment and is outside
the scope of this change.

## Acceptance evidence

The rendered HTTP response from the running Pool Infra YiiMP application was
inspected after the change.

The generated coin options included:

    <option value='ABY' data-port='9000' ...>ArtByte (ABY)</option>

and:

    <option value='NENG' data-port='9000' ...>Nengcoin (NENG)</option>

The HTTP request returned status 200.

PASS: multiple Scrypt coins served by the shared listener independently
advertised the correct algorithm-level port `9000`.

The release candidate subsequently built and deployed successfully with this
source change included.

## Acceptance result

PASS.

The UI now represents Pool Infra's shared algorithm-listener architecture
without requiring or implying per-coin Stratum ports.

## Upstream disposition

Required downstream behavior while Pool Infra uses algorithm-level shared
Stratum listeners and upstream UI logic does not represent that topology
correctly.

For future upstream imports, inspect whether upstream provides an equivalent
algorithm-level listener lookup.

Retire this downstream change if upstream provides the required behavior.

## Implementation references

Modified release file:

- `web/yaamp/modules/site/index.php`
  - obtains miner connection port using `getAlgoPort($algo)`

Accepted implementation release:

    74988f92.0003
