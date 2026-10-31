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

namespace altay\bedrockstructure\palette;

use altay\bedrockstructure\StructureException;
use pocketmine\nbt\tag\CompoundTag;
use function array_key_first;
use function count;

/**
 * All named palettes of a structure plus the one currently in use.
 *
 * Palettes nobody asked for stay as raw NBT and get written back untouched, most files only ever
 * use "default" anyway.
 */
final class PaletteSet{
	public const DEFAULT = "default";

	private Palette $active;
	private string $activeName;

	/**
	 * Picks "default" as the active palette, or the first one if there's none by that name.
	 *
	 * @param CompoundTag[]|Palette[] $palettes
	 * @phpstan-param array<string, CompoundTag|Palette> $palettes
	 *
	 * @throws StructureException if the palette it picks is malformed
	 */
	public function __construct(
		private array $palettes
	){
		// ok so grabbing the first one beats inventing an air-only "default", that one would break the
		// "all palettes same length" rule on the next read
		$this->use(isset($this->palettes[self::DEFAULT]) || count($this->palettes) === 0 ? self::DEFAULT : (string) array_key_first($this->palettes));
	}

	/**
	 * A set with just an air-only "default" palette, for new structures.
	 */
	public static function create() : self{
		return new self([self::DEFAULT => Palette::create()]);
	}

	/**
	 * @throws StructureException
	 */
	public static function fromNbt(CompoundTag $tag) : self{
		if($tag->count() === 0){
			throw new StructureException("Structure has no palettes in it");
		}
		$palettes = [];
		$length = null;
		foreach($tag->getValue() as $name => $palette){
			if(!$palette instanceof CompoundTag){
				throw new StructureException("Palette \"$name\" must be a compound");
			}
			$paletteLength = $palette->getListTag(Palette::TAG_BLOCK_PALETTE)?->count() ?? 0;
			if($length !== null && $paletteLength !== $length){
				throw new StructureException("All palettes must have the same length, but got one with length $length and one with length $paletteLength");
			}
			$length = $paletteLength;
			$palettes[(string) $name] = $palette;
		}
		return new self($palettes);
	}

	public function toNbt() : CompoundTag{
		$tag = CompoundTag::create();
		foreach($this->palettes as $name => $palette){
			$tag->setTag($name, $palette instanceof Palette ? $palette->toNbt() : $palette);
		}
		return $tag;
	}

	/**
	 * Makes the named palette the active one. One that doesn't exist yet is created with just air in it.
	 *
	 * @throws StructureException if the palette exists but is malformed
	 */
	public function use(string $name) : void{
		$palette = $this->palettes[$name] ?? null;
		if(!$palette instanceof Palette){
			$palette = $palette === null ? Palette::create() : Palette::fromNbt($palette);
			$this->palettes[$name] = $palette;
		}
		$this->active = $palette;
		$this->activeName = $name;
	}

	public function getActive() : Palette{
		return $this->active;
	}

	public function getActiveName() : string{
		return $this->activeName;
	}
}
