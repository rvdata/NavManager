import sys
import datetime
import math


def calculate_distance(p1, p2, p):
    """
    Calculate the perpendicular distance from a point to a line.

    Args:
    - p1, p2: Tuples representing the endpoints of the line (x, y).
    - p: Tuple representing the point (x, y).

    Returns:
    - The perpendicular distance from the point to the line.
    """
    x1 = p1.lon
    y1 = p1.lat
    x2 = p2.lon
    y2 = p2.lat
    x = p.lon
    y = p.lat

    if (x1 == x2) and (y1 == y2):
        # p1 and p2 are the same point, so return the Euclidean distance from p to p1 (or p2)
        return math.sqrt((x - x1) ** 2 + (y - y1) ** 2)

    # Compute the perpendicular distance using the line equation
    numerator = abs((y2 - y1) * x - (x2 - x1) * y + x2 * y1 - y2 * x1)
    denominator = math.sqrt((y2 - y1) ** 2 + (x2 - x1) ** 2)
    distance = numerator / denominator

    return distance


def douglas_peucker(points, tolerance):
    """
    Simplify a polyline using the Douglas-Peucker algorithm.

    Args:
    - points: List of Coordinate objects representing the polyline.
    - tolerance: The maximum allowable deviation.

    Returns:
    - A simplified list of Coordinate objects.
    """
    if len(points) < 2:
        # Not enough points to simplify, return as is
        return points

    # Find the point with the maximum distance from the line formed by the first and last points
    max_distance = 0
    max_distance_index = 1
    for i in range(1, len(points) - 1):
        distance = calculate_distance(points[0], points[-1], points[i])
        if distance > max_distance:
            max_distance = distance
            max_distance_index = i

    if max_distance > tolerance:
        # Recursively simplify the segments
        left_simplified = douglas_peucker(points[:max_distance_index + 1], tolerance)
        right_simplified = douglas_peucker(points[max_distance_index:], tolerance)

        # Combine the results, removing the last point of the left part to avoid duplication
        result = left_simplified[:-1]  # Remove the last point from left part (duplicate)
        result.extend(right_simplified)
    else:
        # The line is sufficiently straight, so return the endpoints
        result = [points[0], points[-1]]

    return result


# Define the Coordinate class similar to the one used in the Java code
class Coordinate:
    def __init__(self, lon, lat, alt="", label=""):
        self.lon = lon
        self.lat = lat
        self.alt = alt
        self.label = label

    def __str__(self):
        return f"Coordinate(label={self.label}, lon={self.lon}, lat={self.lat}, alt={self.alt})"

    def __repr__(self):
        return f"({self.lon}, {self.lat})"


def main():
    if len(sys.argv) != 3:
        # Ensure the correct number of arguments are provided
        print("Usage: python navsimplifier_simpler.py <input_filename> <output_filename>")
        return

    infile_name = sys.argv[1]
    outfile_name = sys.argv[2]
    pts_list = []

    try:
        # Read input file and populate pts_list with Coordinate objects
        with open(infile_name, 'r') as file_in:
            for line in file_in:
                if not line.startswith("//") and not line.startswith("#") and not line.startswith(">"):
                    st = line.strip().split("\t")
                    label = st[0]
                    lon = float(st[1])
                    lat = float(st[2])
                    if lon > 180:
                        lon = lon - 360.0
                    coord = Coordinate(lon, lat, float('nan'), label)
                    pts_list.append(coord)
    except FileNotFoundError:
        print(f"File not found: {infile_name}")
    except Exception as e:
        print(f"An error occurred: {e}")

    print("Number of input samples:", len(pts_list))

    # Run the Douglas-Peucker algorithm
    tolerance = 0.1
    pts_simple = douglas_peucker(pts_list, tolerance)
    print("Simplified points:")

    try:
        # Write the simplified points to the output file
        with open(outfile_name, 'w') as out_file:
            # Write header for ArcGIS import (commented out)
            # out_file.write("lon,lat\n")

            # Write R2R NavControl header
            out_file.write("// Datetime [UTC], Longitude [deg], Latitude [deg]\n")
            out_file.write("// More detailed information may be found here: "
                           "http://get.rvdata.us/format/100002/format-r2rnav.txt\n")

            # Get current UTC datetime and write in RFC5424 format
            format_utc = datetime.datetime.utcnow().strftime("%Y-%m-%dT%H:%M:%S")
            out_file.write(f"// Creation date: {format_utc}Z\n")

            # Write the simplified points
            for point in pts_simple:
                line = f"{point.label}\t{point.lon}\t{point.lat}\n"
                out_file.write(line)

        print(f"Number of output samples: {len(pts_simple)}")
        print("done")
    except Exception as e:
        print(f"Cannot create {outfile_name}")
        print(e)


if __name__ == "__main__":
    main()
