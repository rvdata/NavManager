import java.io.BufferedReader;
import java.io.BufferedWriter;
import java.io.File;
import java.io.FileNotFoundException;
import java.io.FileReader;
import java.io.FileWriter;
import java.io.IOException;
import java.util.ArrayList;
import java.util.List;
import java.util.Vector;
import java.util.Date;
import java.text.SimpleDateFormat;
import java.util.TimeZone;


public class navsimplifier {

	
	public static void main(String[] args) {

		String fileName = args[0];
      String delimiter = args[2];
      String header = args[3];
		
		//--- read data file
		Vector<Coordinate> ptsList = new Vector<Coordinate>(10);
		
		try {
			BufferedReader fileIn = new BufferedReader(
			// new FileReader(new File(dir,fileName)));
					new FileReader(new File(fileName)));
			
			//int i = 0;
			String line;
			while( (line=fileIn.readLine()) != null) {
			    if (!line.startsWith(header)) {  // Skip header records
				String[] st = line.split(delimiter);
				String label = st[0];
				double lon = Double.parseDouble(st[1]);
				double lat = Double.parseDouble(st[2]);
				if (lon > 180) {
				    lon = lon - 360.0;
				};
				//fmt = Integer.parseInt( st.nextToken() );
				//System.out.println(lat+"----"+lon);
				Coordinate coord = new Coordinate(lon, lat, Double.NaN, label);
				ptsList.add(coord);
			    }  // end if not header
			}
		} catch (IOException e) {
				e.printStackTrace();
		}
		
		//--- convert the list into an array, which is needed for the algorithm, I think
		Coordinate[] pts = new Coordinate[ptsList.size()];
		int i;
		for (i = 0; i<ptsList.size(); i++){
			pts[i]= ptsList.get(i);
			//System.out.println(pts[i].x);
		}
		System.out.println("Number input samples: "+i);
		
		//--- run the algorithm
		Coordinate[] ptsSimple;
		DouglasPeuckerLineSimplifier myDPS = new DouglasPeuckerLineSimplifier(pts);
                myDPS.setDistanceTolerance(0.01); 
		//		myDPS.setDistanceTolerance(0.001);
		ptsSimple = myDPS.simplify();
		
		//--- write the simplified data
		String outFileName = args[1];

		try {
			BufferedWriter outFile = new BufferedWriter(
					new FileWriter(new File(outFileName)));
		
			// write header for ArcGIS import
			//outFile.write("lon,lat\n");
			
			// Write R2R NavControl header:
			outFile.write("// Datetime [UTC], Longitude [deg], Latitude [deg]\n");
			outFile.write("// More detailed information may be found here: " 
				      + "http://get.rvdata.us/format/100002/format-r2rnav.txt\n");
			// Get current UTC datetime and write in RFC5424 format:
			SimpleDateFormat formatUTC = new SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss");
			formatUTC.setTimeZone(TimeZone.getTimeZone("UTC"));
			Date date = new Date();
			outFile.write("// Creation date: " + formatUTC.format(date) + "Z\n");

			String line;
			for (i = 0; i<ptsSimple.length; i++){
				line = ptsSimple[i].label+delimiter+Double.toString(ptsSimple[i].x)+delimiter+Double.toString(ptsSimple[i].y)+"\n";
				outFile.write(line);
				//System.out.println(ptsSimple[i].x);
			}
			outFile.close(); //closes and flushes the buffer
		} catch (IOException e) {
			e.printStackTrace();
			System.out.println("Cannot create "+outFileName);
		}
		System.out.println("nr of output samlpes: "+i);
		System.out.println("done");
	}

}
