require 'time'

import 'org.apache.hadoop.hbase.client.HTable'
import 'org.apache.hadoop.hbase.client.Put'

def jbytes(*args)
  args.map { |arg| arg.to_s.to_java_bytes }
end

table = HTable.new(@hbase.configuration, "dicom")

key = *jbytes(tres = ARGV[0])
p = Put.new(key)
path = ARGV[1]
file = contents = IO.read(path)
p.add(*jbytes("file", "", file))
table.put(p)
table.flushCommits()
exit
