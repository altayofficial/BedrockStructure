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

namespace altay\bedrockstructure\rotation;

use pocketmine\math\Axis;
use pocketmine\math\Facing;

/**
 * A quarter turn around the Y axis, seen from above.
 */
enum Rotation{
	case CLOCKWISE;
	case COUNTER_CLOCKWISE;

	public function isClockwise() : bool{
		return $this === self::CLOCKWISE;
	}

	/**
	 * Turns a horizontal face, up and down stay as they are.
	 */
	public function rotateFace(int $face) : int{
		return Facing::axis($face) === Axis::Y ? $face : Facing::rotateY($face, $this->isClockwise());
	}

	/**
	 * Where (x, z) ends up in the turned area, given the size of the area before turning.
	 *
	 * @phpstan-return array{int, int}
	 */
	public function rotatePosition(int $x, int $z, int $sizeX, int $sizeZ) : array{
		return $this === self::CLOCKWISE ?
			[$sizeZ - 1 - $z, $x] :
			[$z, $sizeX - 1 - $x];
	}
}
