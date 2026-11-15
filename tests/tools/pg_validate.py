"""PostgreSQL validation (needs psycopg2 and a THROWAWAY PostgreSQL server; creates/drops database erp_pg).

  APP_TESTING=1 php tests/tools/pg_statements.php > /tmp/pg/stmts.json            # translated migrations
  ERP_SQL_CAPTURE=/tmp/pg/captured.jsonl APP_TESTING=1 (run the integration suites on MySQL)
  php tests/tools/pg_translate_captured.php /tmp/pg/captured.jsonl > /tmp/pg/captured_pg.json
  PGHOST=127.0.0.1 PGPORT=5432 PGUSER=postgres python3 tests/tools/pg_validate.py apply|prepare|triggers

apply    : applies every translated migration to a fresh database (each file in a transaction)
prepare  : PREPAREs every captured application query (syntax, names, GROUP BY rules) without running it
manifest : compares the live PostgreSQL schema with database/schema-manifest.json (columns, nullability, unique keys, FK rules)
triggers : checks PRN immutability / registry append-only triggers and constraints
Statements captured from a MySQL run that use MySQL-only forms (ON DUPLICATE KEY, migrations table) are expected to be reported;
their PostgreSQL forms come from Core/Database/Dialect.
"""
import os, sys
def run_apply():
    import json, psycopg2, sys
    d=json.load(open('/tmp/pg/stmts.json'))
    adm=psycopg2.connect(host=os.environ.get('PGHOST','127.0.0.1'),port=int(os.environ.get('PGPORT','5432')),user=os.environ.get('PGUSER','postgres'),dbname='postgres'); adm.autocommit=True
    c=adm.cursor(); c.execute('DROP DATABASE IF EXISTS erp_pg'); c.execute('CREATE DATABASE erp_pg'); adm.close()
    conn=psycopg2.connect(host=os.environ.get('PGHOST','127.0.0.1'),port=int(os.environ.get('PGPORT','5432')),user=os.environ.get('PGUSER','postgres'),dbname='erp_pg')
    fails=0
    for name,stmts in d.items():
        cur=conn.cursor()
        try:
            for s in stmts: 
                try: cur.execute(s)
                except Exception as e:
                    print('FAIL',name,'\n  ',s[:300].replace('\n',' '),'\n  ->',str(e).strip()[:200]); raise
            conn.commit()
        except Exception:
            conn.rollback(); fails+=1
    print('files failed:',fails,'of',len(d))
    cur=conn.cursor(); cur.execute("select count(*) from information_schema.tables where table_schema='public'"); print('tables',cur.fetchone())
def run_prepare():
    import json, re, psycopg2, collections
    items=json.load(open('/tmp/pg/captured_pg.json'))
    conn=psycopg2.connect(host=os.environ.get('PGHOST','127.0.0.1'),port=int(os.environ.get('PGPORT','5432')),user=os.environ.get('PGUSER','postgres'),dbname='erp_pg'); conn.autocommit=True
    cur=conn.cursor()
    def numbered(sql):
        out=[];n=0;i=0;inq=False
        while i<len(sql):
            ch=sql[i]
            if ch=="'":
                inq=not inq
            if ch=='?' and not inq: n+=1; out.append('$%d'%n)
            else: out.append(ch)
            i+=1
        return ''.join(out),n
    errs=collections.Counter(); ex={}; ok=0; skipped=0
    for it in items:
        sql=it['pg']
        if re.match(r'^\s*(SAVEPOINT|RELEASE|ROLLBACK|SET |CREATE |DROP |ALTER |SELECT 1\b|SHOW|INSERT INTO `migrations`|SELECT `migration`)',sql,re.I): skipped+=1; continue
        q,n=numbered(sql)
        try:
            cur.execute('PREPARE s AS '+q); cur.execute('DEALLOCATE s'); ok+=1
        except Exception as e:
            try: cur.execute('DEALLOCATE s')
            except Exception: pass
            msg=str(e).strip().split('\n')[0]
            key=re.sub(r'"[^"]*"','"x"',msg)
            errs[key]+=1; ex.setdefault(key,[]).append(it['orig'][:260])
    print('prepared OK:',ok,' skipped:',skipped,' errors:',sum(errs.values()))
    for k,v in errs.most_common(): print('\n',v,'x',k,'\n    e.g.',ex[k][0].replace('\n',' '))
def run_triggers():
    import psycopg2
    conn=psycopg2.connect(host=os.environ.get('PGHOST','127.0.0.1'),port=int(os.environ.get('PGPORT','5432')),user=os.environ.get('PGUSER','postgres'),dbname='erp_pg'); conn.autocommit=True; cur=conn.cursor()
    cur.execute("""INSERT INTO students (admission_no, first_name, last_name, prn) VALUES ('T-1','a','b','202611150001') RETURNING id""")
    sid=cur.fetchone()[0]
    def tryit(label,q):
        try: cur.execute(q); print(label,'-> ALLOWED (unexpected)')
        except Exception as e: print(label,'-> blocked:',str(e).split('\n')[0])
    tryit('change PRN', f"UPDATE students SET prn='999' WHERE id={sid}")
    cur.execute(f"UPDATE students SET first_name='zed' WHERE id={sid}"); print('routine update -> allowed')
    cur.execute("INSERT INTO prn_registry (prn, issued_at) VALUES ('202611150001', now())")
    tryit('duplicate registry PRN',"INSERT INTO prn_registry (prn, issued_at) VALUES ('202611150001', now())")
    tryit('registry update',"UPDATE prn_registry SET notes='x'")
    tryit('registry delete',"DELETE FROM prn_registry")
    tryit('duplicate student PRN',"INSERT INTO students (admission_no, first_name, last_name, prn) VALUES ('T-2','a','b','202611150001')")
    tryit('ENUM check',"INSERT INTO students (admission_no, first_name, last_name, gender) VALUES ('T-3','a','b','robot')")

def run_manifest():
    import json, psycopg2
    m=json.load(open(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', 'database', 'schema-manifest.json')))
    c=psycopg2.connect(host=os.environ.get('PGHOST','127.0.0.1'),port=int(os.environ.get('PGPORT','55432')),user='postgres',dbname='erp_pg'); cur=c.cursor()
    cur.execute("select table_name,column_name,is_nullable from information_schema.columns where table_schema='public'")
    cols={}
    for t,col,n in cur.fetchall(): cols.setdefault(t,{})[col]=(n=='YES')
    cur.execute("""select t.relname, i.relname, array_agg(a.attname order by a.attname) from pg_index ix join pg_class t on t.oid=ix.indrelid join pg_class i on i.oid=ix.indexrelid join pg_namespace n on n.oid=t.relnamespace join pg_attribute a on a.attrelid=t.oid and a.attnum=any(ix.indkey) where ix.indisunique and not ix.indisprimary and n.nspname='public' group by 1,2""")
    uq={}
    for t,i,a in cur.fetchall(): uq.setdefault(t,set()).add(','.join(a))
    cur.execute("""select kcu.table_name,kcu.column_name,ccu.table_name,ccu.column_name,rc.delete_rule from information_schema.referential_constraints rc join information_schema.key_column_usage kcu on kcu.constraint_name=rc.constraint_name and kcu.constraint_schema=rc.constraint_schema join information_schema.constraint_column_usage ccu on ccu.constraint_name=rc.constraint_name and ccu.constraint_schema=rc.constraint_schema where rc.constraint_schema='public'""")
    fk={}
    for t,col,pt,pc,d in cur.fetchall(): fk[(t,col,pt+'.'+pc)]=d
    issues=[]
    for t,d in m['tables'].items():
        if t not in cols: issues.append(('missing table',t)); continue
        for col,meta in d['columns'].items():
            if col not in cols[t]: issues.append(('missing col',t,col))
            elif cols[t][col]!=meta['null']: issues.append(('nullability',t,col))
        for u in d['unique']:
            if u not in uq.get(t,set()): issues.append(('missing unique',t,u))
        for f in d['foreign_keys']:
            got=fk.get((t,f['column'],f['ref']))
            if got is None: issues.append(('missing fk',t,f['column']))
            elif got!=f['on_delete']: issues.append(('fk rule',t,f['column'],got,f['on_delete']))
    print('tables in manifest:',len(m['tables']),'issues:',len(issues))
    for i in issues[:15]: print(' ',i)

if __name__=='__main__':
    {'apply':run_apply,'prepare':run_prepare,'triggers':run_triggers,'manifest':run_manifest}[sys.argv[1]]()
