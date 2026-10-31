<?php

/*
 *
 *      _    _ _
 *     / \  | | |_ __ _ _   _
 *    / _ \ | | __/ _` | | | |
 *   / ___ \| | || (_| | |_| |
 *  /_/   \_\_|\__\__,_|\__, |
 *                       |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author Altay Team
 * @link https://github.com/altayofficial
 */


declare(strict_types=1);

namespace altay\bedrockstructure;

use altay\bedrockstructure\io\Compression;
use altay\bedrockstructure\io\FormatVersion;
use altay\bedrockstructure\io\StructureReader;
use altay\bedrockstructure\io\StructureWriter;
use altay\bedrockstructure\palette\PaletteSet;
use altay\bedrockstructure\rotation\Rotation;
use altay\bedrockstructure\rotation\StructureRotator;
use altay\bedrockstructure\storage\BlockStorage;
use pocketmine\block\Block;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\tag\CompoundTag;

/**
 * Holds the contents of a .mcstructure file and lets you read and change the blocks in it.
 *
 * Blocks are stored the same way Bedrock does it: two layers of palette indices (the block itself and
 * an optional liquid it's waterlogged with) and one or more named palettes. All block access goes
 * through the palette picked with usePalette(). That starts out as "default", or the first palette in the
 * file if it has none by that name.
 */
final class Structure{
	public const DEFAULT_PALETTE = PaletteSet::DEFAULT;

	/**
	 * You probably want create() or read() instead, this is what they and the rotator build on.
	 *
	 * @param int[]         $origin
	 * @param CompoundTag[] $entities
	 * @phpstan-param array{int, int, int} $origin
	 * @phpstan-param list<CompoundTag> $entities
	 */
	public function __construct(
		private BlockStorage $storage,
		private PaletteSet $palettes,
		private array $origin = [0, 0, 0],
		private array $entities = [],
		private FormatVersion $formatVersion = FormatVersion::V2,
		private Compression $compression = Compression::NONE
	){}

	/**
	 * Creates an empty structure of the given size, filled with air and without any liquids.
	 */
	public static function create(int $sizeX, int $sizeY, int $sizeZ) : self{
		// air is the only entry of a fresh palette, so index 0
		return new self(BlockStorage::filled($sizeX, $sizeY, $sizeZ, 0), PaletteSet::create());
	}

	/**
	 * Decodes a structure from the raw bytes of a .mcstructure file.
	 *
	 * @throws StructureException
	 */
	public static function read(string $data) : self{
		return (new StructureReader())->read($data);
	}

	/**
	 * @throws StructureException
	 */
	public static function readFile(string $path) : self{
		return (new StructureReader())->readFile($path);
	}

	/**
	 * Encodes the structure into the bytes of a .mcstructure file.
	 *
	 * @throws \LogicException if compression is enabled on format version 1
	 */
	public function write() : string{
		return (new StructureWriter())->write($this);
	}

	/**
	 * Writes the structure to a file, replacing it if it already exists.
	 *
	 * @throws StructureException
	 */
	public function writeFile(string $path) : void{
		(new StructureWriter())->writeFile($this, $path);
	}

	/**
	 * Switches the palette that blocks are read from and written to. A palette that doesn't exist yet
	 * is created with just air in it, and gets written out under this name.
	 *
	 * @throws StructureException if the palette exists but is malformed
	 */
	public function usePalette(string $name) : void{
		$this->palettes->use($name);
	}

	public function getPaletteName() : string{
		return $this->palettes->getActiveName();
	}

	/**
	 * Format version used by write(). Always starts out as version 2, also for structures read from
	 * version 1 files, so writing upgrades them. Switch to version 1 if clients older than 1.26.50 need to
	 * load the file.
	 */
	public function getFormatVersion() : FormatVersion{
		return $this->formatVersion;
	}

	public function setFormatVersion(FormatVersion $version) : void{
		$this->formatVersion = $version;
	}

	public function getCompression() : Compression{
		return $this->compression;
	}

	/**
	 * Sets how the structure tag gets compressed on write. Only format version 2 supports compression,
	 * and structures read from a compressed file keep it.
	 */
	public function setCompression(Compression $compression) : void{
		$this->compression = $compression;
	}

	/**
	 * @return int[]
	 * @phpstan-return array{int, int, int}
	 */
	public function getDimensions() : array{
		return [$this->storage->getSizeX(), $this->storage->getSizeY(), $this->storage->getSizeZ()];
	}

	/**
	 * Returns the world position the structure was saved from. Purely informational, nothing here uses it.
	 *
	 * @return int[]
	 * @phpstan-return array{int, int, int}
	 */
	public function getOrigin() : array{
		return $this->origin;
	}

	public function setOrigin(int $x, int $y, int $z) : void{
		$this->origin = [$x, $y, $z];
	}

	/**
	 * Returns the block at the position, or null if the structure leaves that spot untouched (index -1,
	 * like structure void).
	 */
	public function getBlockAt(int $x, int $y, int $z) : ?Block{
		$index = $this->storage->getBlock($this->storage->offset($x, $y, $z));
		return $index === BlockStorage::NO_BLOCK ? null : $this->palettes->getActive()->getBlock($index);
	}

	/**
	 * Returns the liquid the block at the position is waterlogged with, if any.
	 */
	public function getLiquidAt(int $x, int $y, int $z) : ?Block{
		$index = $this->storage->getLiquid($this->storage->offset($x, $y, $z));
		return $index === BlockStorage::NO_BLOCK ? null : $this->palettes->getActive()->getBlock($index);
	}

	/**
	 * Returns the raw block state at the position as stored in the palette (after upgrading). Unlike
	 * getBlockAt() this still works for blocks the server doesn't know about.
	 */
	public function getBlockStateDataAt(int $x, int $y, int $z) : ?BlockStateData{
		$index = $this->storage->getBlock($this->storage->offset($x, $y, $z));
		return $index === BlockStorage::NO_BLOCK ? null : $this->palettes->getActive()->getStateData($index);
	}

	/**
	 * Returns a copy of the block entity NBT stored for the position, if there is any. Coordinates inside
	 * it are whatever they were when the structure was saved, so fix them up before loading a tile.
	 */
	public function getBlockEntityDataAt(int $x, int $y, int $z) : ?CompoundTag{
		return $this->palettes->getActive()->getBlockEntityData($this->storage->offset($x, $y, $z));
	}

	/**
	 * Sets the block at the position. Pass null as the block to leave the spot untouched when the
	 * structure is placed. Any block entity data previously stored there is replaced by $blockEntityData.
	 */
	public function setBlockAt(int $x, int $y, int $z, ?Block $block, ?Block $liquid = null, ?CompoundTag $blockEntityData = null) : void{
		$offset = $this->storage->offset($x, $y, $z);
		$palette = $this->palettes->getActive();
		$this->storage->setBlock($offset, $block === null ? BlockStorage::NO_BLOCK : $palette->indexOf($block));
		$this->storage->setLiquid($offset, $liquid === null ? BlockStorage::NO_BLOCK : $palette->indexOf($liquid));
		$palette->setBlockEntityData($offset, $blockEntityData);
	}

	/**
	 * @return CompoundTag[]
	 * @phpstan-return list<CompoundTag>
	 */
	public function getEntities() : array{
		return $this->entities;
	}

	/**
	 * @param CompoundTag[] $entities
	 * @phpstan-param list<CompoundTag> $entities
	 */
	public function setEntities(array $entities) : void{
		$this->entities = $entities;
	}

	/**
	 * Returns a copy of the structure turned 90 degrees anti-clockwise (seen from above).
	 */
	public function rotateLeft() : self{
		return (new StructureRotator())->rotate($this, Rotation::COUNTER_CLOCKWISE);
	}

	/**
	 * Returns a copy of the structure turned 90 degrees clockwise (seen from above).
	 */
	public function rotateRight() : self{
		return (new StructureRotator())->rotate($this, Rotation::CLOCKWISE);
	}

	/**
	 * @internal
	 */
	public function getStorage() : BlockStorage{
		return $this->storage;
	}

	/**
	 * @internal
	 */
	public function getPalettes() : PaletteSet{
		return $this->palettes;
	}
}
