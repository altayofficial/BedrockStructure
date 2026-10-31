# BedrockStructure

Library for reading, writing and editing Minecraft Bedrock Edition `.mcstructure` files on Altay.

## Installation

```shell
composer require altayofficial/bedrockstructure
```

## Usage

```php
use altay\bedrockstructure\Structure;
use pocketmine\block\VanillaBlocks;

$structure = Structure::readFile("house.mcstructure");
[$sizeX, $sizeY, $sizeZ] = $structure->getDimensions();

$block = $structure->getBlockAt(0, 0, 0);       // null means "leave the world alone here"
$liquid = $structure->getLiquidAt(0, 0, 0);     // what the block is waterlogged with, if anything
$tile = $structure->getBlockEntityDataAt(0, 0, 0);

$structure->setBlockAt(1, 0, 0, VanillaBlocks::STONE());
$structure->rotateRight()->writeFile("house_rotated.mcstructure");
```

New structures start out filled with air:

```php
$structure = Structure::create(16, 8, 16);
$structure->setBlockAt(0, 0, 0, VanillaBlocks::CHEST(), null, $chestNbt);
$structure->writeFile("chest.mcstructure");
```

Block access goes through one palette at a time. That's `default` unless the file only has other palettes, in which case the first one is used. Switch with `usePalette()`; a palette that doesn't exist yet starts with just air and gets written under that name.

### Format versions

Both format versions are read. Version 1 has exactly two block index layers stored as int lists, which is what vanilla packs ship. Version 2 came with 1.26.50: one or two int array layers (the liquid layer is left out when nothing is waterlogged) and an optional zlib compressed structure tag.

Everything is written as version 2, so reading and writing a version 1 file upgrades it. Call `setFormatVersion(Structure::FORMAT_VERSION_1)` if older clients need to load the file. Compressed files stay compressed when written back; toggle it with `setCompressed()`, which only works with version 2.

### Things to know

* Blocks the server doesn't know show up as the unknown block (`info_update`), but their original state is kept and written back unchanged. `getBlockStateDataAt()` gives you the raw state.
* Rotation turns facing, axis, sign rotation, multi face, horizontal connection, wall and lever states. Other states, like rail shapes, keep their orientation. Entities are dropped from rotated copies since their positions are absolute.
* Block entity NBT is stored as saved. Its `x`, `y` and `z` still point at where the structure was exported from, so fix them up before loading a tile from it.
* Altay has no waterlogging yet, so the liquid layer is only data for now.

## Development

```shell
composer install
composer test
composer analyse
```
