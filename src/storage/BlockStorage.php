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

namespace altay\bedrockstructure\storage;

use function array_fill;
use function count;
use function intdiv;
use function max;
use function min;

/**
 * The two block index layers of a structure: the block itself and the liquid it's waterlogged with.
 * Indices point into the active palette, and NO_BLOCK means "leave whatever is in the world here".
 *
 * Positions are flattened the Bedrock way, x is the slowest axis and z the fastest.
 */
final class BlockStorage{
	public const NO_BLOCK = -1;

	private int $volume;

	/**
	 * @param int[] $blocks
	 * @param int[] $liquids
	 * @phpstan-param array<int, int> $blocks
	 * @phpstan-param array<int, int> $liquids
	 */
	public function __construct(
		private int $sizeX,
		private int $sizeY,
		private int $sizeZ,
		private array $blocks,
		private array $liquids
	){
		if($sizeX <= 0 || $sizeY <= 0 || $sizeZ <= 0){
			throw new \InvalidArgumentException("Structure size must be positive, got {$sizeX}x{$sizeY}x{$sizeZ}");
		}
		$this->volume = $sizeX * $sizeY * $sizeZ;
		if(count($blocks) !== $this->volume || count($liquids) !== $this->volume){
			throw new \InvalidArgumentException("A {$sizeX}x{$sizeY}x{$sizeZ} storage needs {$this->volume} indices per layer");
		}
	}

	/**
	 * Storage with every position set to the same block and liquid index.
	 */
	public static function filled(int $sizeX, int $sizeY, int $sizeZ, int $block, int $liquid = self::NO_BLOCK) : self{
		if($sizeX <= 0 || $sizeY <= 0 || $sizeZ <= 0){
			throw new \InvalidArgumentException("Structure size must be positive, got {$sizeX}x{$sizeY}x{$sizeZ}");
		}
		$volume = $sizeX * $sizeY * $sizeZ;
		return new self($sizeX, $sizeY, $sizeZ, array_fill(0, $volume, $block), array_fill(0, $volume, $liquid));
	}

	public function getSizeX() : int{ return $this->sizeX; }

	public function getSizeY() : int{ return $this->sizeY; }

	public function getSizeZ() : int{ return $this->sizeZ; }

	public function getVolume() : int{ return $this->volume; }

	/**
	 * @throws \InvalidArgumentException if the position is outside of the storage
	 */
	public function offset(int $x, int $y, int $z) : int{
		if($x < 0 || $x >= $this->sizeX || $y < 0 || $y >= $this->sizeY || $z < 0 || $z >= $this->sizeZ){
			throw new \InvalidArgumentException("Position x=$x,y=$y,z=$z is outside of the {$this->sizeX}x{$this->sizeY}x{$this->sizeZ} structure");
		}
		return ($x * $this->sizeZ * $this->sizeY) + ($y * $this->sizeZ) + $z;
	}

	/**
	 * Turns an offset back into a position, the inverse of offset().
	 *
	 * @phpstan-return array{int, int, int}
	 */
	public function position(int $offset) : array{
		$layer = $this->sizeZ * $this->sizeY;
		return [intdiv($offset, $layer), intdiv($offset % $layer, $this->sizeZ), $offset % $this->sizeZ];
	}

	public function getBlock(int $offset) : int{
		return $this->blocks[$offset];
	}

	public function setBlock(int $offset, int $index) : void{
		$this->blocks[$offset] = $index;
	}

	public function getLiquid(int $offset) : int{
		return $this->liquids[$offset];
	}

	public function setLiquid(int $offset, int $index) : void{
		$this->liquids[$offset] = $index;
	}

	/**
	 * @return int[]
	 * @phpstan-return array<int, int>
	 */
	public function getBlocks() : array{
		return $this->blocks;
	}

	/**
	 * @return int[]
	 * @phpstan-return array<int, int>
	 */
	public function getLiquids() : array{
		return $this->liquids;
	}

	/**
	 * Whether anything at all is waterlogged.
	 */
	public function hasLiquids() : bool{
		return count($this->liquids) > 0 && (min($this->liquids) !== self::NO_BLOCK || max($this->liquids) !== self::NO_BLOCK);
	}

	/**
	 * Checks that both layers only point at existing palette entries.
	 */
	public function fitsPalette(int $paletteSize) : bool{
		foreach([$this->blocks, $this->liquids] as $layer){
			if(count($layer) > 0 && (min($layer) < self::NO_BLOCK || max($layer) >= $paletteSize)){
				return false;
			}
		}
		return true;
	}
}
