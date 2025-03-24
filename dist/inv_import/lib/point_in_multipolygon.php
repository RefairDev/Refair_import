<?php

namespace XlsInventory;

class pointLocation {


	function booleanPointInPolygon( $point, $polygon, $options = array() ) {
		// validation
		if ( ! isset( $point ) && ! empty( $point ) ) {
			throw new \Error( 'point is required' );
		}

		if ( ! isset( $polygon ) && ! empty( $polygon ) ) {
			throw new \Error( 'polygon is required' );
		}
		$pt   = $this->getCoord( $point );
		$geom = $this->getGeom( $polygon );
		$type = null;
		if ( array_key_exists( 'type', $geom ) ) {
			$type = $geom['type'];}

		$bbox = null;
		if ( array_key_exists( 'bbox', $polygon ) ) {
			$bbox = $polygon['bbox'];}
		$polys = null;
		if ( array_key_exists( 'coordinates', $geom ) ) {
			$polys = $geom['coordinates'];}
		// Quick elimination if point is not inside bbox
		if ( isset( $bbox ) && $this->inBBox( $pt, $bbox ) === false ) {
			return false;
		}
		// normalize to multipolygon
		if ( $type === 'Polygon' ) {
			$polys = array( $polys );
		}
		$insidePoly     = false;
		$ignoreBoundary = false;
		if ( array_key_exists( 'ignoreBoundary', $options ) ) {
			$ignoreBoundary = $options['ignoreBoundary'];}
		for ( $i = 0; $i < count( $polys ) && ! $insidePoly; $i++ ) {
			// check if it is in the outer ring first
			if ( $this->inRing( $pt, $polys[ $i ][0], $ignoreBoundary ) ) {
				$inHole = false;
				$k      = 1;
				// check for the point in any of the holes
				while ( $k < count( $polys[ $i ] ) && ! $inHole ) {
					if ( $this->inRing( $pt, $polys[ $i ][ $k ], ! $ignoreBoundary ) ) {
						$inHole = true;
					}
					++$k;
				}
				if ( ! $inHole ) {
					$insidePoly = true;
				}
			}
		}
		return $insidePoly;
	}
	/**
	 * inRing
	 *
	 * @private
	 * @param {Array<number>} pt [x,y]
	 * @param {Array<Array<number>>}   $ring [[x,y], [x,y],..]
	 * @param {boolean}                $ignoreBoundary $ignoreBoundary
	 * @returns {boolean} inRing
	 */
	protected function inRing( $pt, $ring, $ignoreBoundary ) {
		$isInside   = false;
		$ring_count = count( $ring );
		if ( $ring[0][0] === $ring[ $ring_count - 1 ][0] &&
			$ring[0][1] === $ring[ $ring_count - 1 ][1] ) {
			$ring = array_slice( $ring, 0, $ring_count - 1 );
		}
		$ring_count = count( $ring );
		for ( $i = 0, $j = $ring_count - 1; $i < $ring_count;  $j = $i, $i++ ) {
			$xi         = $ring[ $i ][0];
			$yi         = $ring[ $i ][1];
			$xj         = $ring[ $j ][0];
			$yj         = $ring[ $j ][1];
			$onBoundary = $pt[1] * ( $xi - $xj ) + $yi * ( $xj - $pt[0] ) + $yj * ( $pt[0] - $xi ) === 0 &&
				( $xi - $pt[0] ) * ( $xj - $pt[0] ) <= 0 &&
				( $yi - $pt[1] ) * ( $yj - $pt[1] ) <= 0;
			if ( $onBoundary ) {
				return ! $ignoreBoundary;
			}
			$intersect = $yi > $pt[1] !== $yj > $pt[1] &&
				$pt[0] < ( ( $xj - $xi ) * ( $pt[1] - $yi ) ) / ( $yj - $yi ) + $xi;
			if ( $intersect ) {
				$isInside = ! $isInside;
			}
		}
		return $isInside;
	}
	/**
	 * inBBox
	 *
	 * @private
	 * @param {Position}                                  $pt point [x,y]
	 * @param {BBox} bbox BBox [west, south, east, north]
	 * @returns {boolean} true/false if point is inside BBox
	 */
	protected function inBBox( $pt, $bbox ) {
		return ( $bbox[0] <= $pt[0] && $bbox[1] <= $pt[1] && $bbox[2] >= $pt[0] && $bbox[3] >= $pt[1] );
	}




	protected function getCoord( $coord ) {
		if ( ! $coord ) {
			throw new \Error( 'coord is required' );
		}
		if ( is_array( $coord ) ) {
			if ( array_key_exists( 'type', $coord ) ) {
				if ( $coord['type'] === 'Feature' &&
					$coord['geometry'] !== null &&
					$coord['geometry']['type'] === 'Point' ) {
					return $coord['geometry']['coordinates'];
				}
				if ( $coord['type'] === 'Point' ) {
					return $coord['coordinates'];
				}
			}

			if ( is_array( $coord ) &&
				count( $coord ) >= 2 &&
				! is_nan( $coord[0] ) &&
				! is_nan( $coord[1] ) ) {
				return $coord;
			}
		}
		throw new \Error( 'coord must be GeoJSON Point or an Array of numbers' );
	}

	protected function getGeom( $geojson ) {
		if ( $geojson['type'] === 'Feature' ) {
			return $geojson['geometry'];
		}
		return $geojson;
	}

	public function point( $coordinates, $properties = array(), $options = array() ) {
		if ( ! $coordinates ) {
			throw new \Error( 'coordinates is required' );
		}
		if ( ! is_array( $coordinates ) ) {
			throw new \Error( 'coordinates must be an Array' );
		}
		if ( count( $coordinates ) < 2 ) {
			throw new \Error( 'coordinates must be at least 2 numbers long' );
		}
		if ( is_nan( $coordinates[0] ) || is_nan( $coordinates[1] ) ) {
			throw new \Error( 'coordinates must contain numbers' );
		}
		$geom = array(
			'type'        => 'Point',
			'coordinates' => $coordinates,
		);
		return $this->feature( $geom, $properties, $options );
	}

	public function feature( $geom, $properties = array(), $options = array() ) {
		$feat = array( 'type' => 'Feature' );
		if ( array_key_exists( 'id', $options ) || ( array_key_exists( 'id', $options ) && $options['id'] === 0 ) ) {
			$feat['id'] = $options['id'];
		}
		if ( array_key_exists( 'bbox', $options ) ) {
			$feat['bbox'] = $options['bbox'];
		}
		$feat['properties'] = $properties;
		$feat['geometry']   = $geom;
		return $feat;
	}
}
