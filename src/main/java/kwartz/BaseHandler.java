/*
 * $Rev$
 * $Release$
 * $Copyright$
 */
package kwartz;

import java.util.List;
import java.util.ArrayList;
import java.util.Map;
import java.util.HashMap;
import java.util.Iterator;
import java.util.regex.Pattern;
import java.util.regex.Matcher;



class HandlerHelper {
	
	//// converter helper
	
	public ConvertException convertError(String message, int linenum) {
		return new ConvertException(message, null, linenum);
	}
	
	public void errorIfEmptyTag(ElementInfo elem_info, String directive_str) throws ConvertException {
		if (elem_info.getEtagInfo() == null) {
			String msg = "'"+directive_str+"': "+" directive is not available with empty tag.";
			throw convertError(msg, elem_info.getStagInfo().getLinenum());
		}
	}
	
	public void errorWhenLastStmtIsNotIf(ElementInfo elem_info, String directive_str, List stmt_list) throws ConvertException {
		int t = _lastStatementToken(stmt_list);
		if (t != Token.IF && t != Token.ELSEIF) {
			String msg = "'"+directive_str+"': previous statement should be 'if' or 'elseif'.";
			throw convertError(msg, elem_info.getStagInfo().getLinenum());
		}
	}
	
	public int _lastStatementToken(List stmt_list) {
		if (stmt_list == null || stmt_list.size() == 0)
			return -1;
		Ast.Statement last_stmt = (Ast.Statement)stmt_list.get(stmt_list.size() - 1);
		return last_stmt.getToken();
	}
	
	//// statement heldper
	
	public Ast.PrintStatement createTextPrintStatement(String text) {
		Ast.Expression expr = new Ast.StringLiteral(text);
		return new Ast.PrintStatement(new Ast.Expression[] { expr });
	}
	
	public List buildPrintStatementArguments(TagInfo taginfo, AttrInfo attr_info, List append_exprs) {
		List args = new ArrayList();
		if (taginfo.getTagName() == null)
			return args;
		if (attr_info == null && append_exprs == null) {
			args.add(new Ast.StringLiteral(taginfo.getTagText()));  // or list.add(taginfo.getTagText()) ?
			return args;
		}
		TagInfo t = taginfo;
		StringBuffer sb = new StringBuffer();
		String s;
		if ((s = t.getHeadSpace()) != null) sb.append(s);
		sb.append('<');
		if (t.isEtag()) sb.append('/');
		sb.append(t.getTagName());
		List names = attr_info.getNames();
		for (Iterator it = names.iterator(); it.hasNext(); ) {
			String name = (String)it.next();
			Object value = attr_info.getValue(name);
			String space = attr_info.getSpace(name);
			sb.append(space).append(name).append("=\"");
			if (value instanceof Ast.Expression) {
				args.add(new Ast.StringLiteral(sb.toString()));
				args.add(value);
				sb.setLength(0);
			}
			else {
				sb.append((String)value);
			}
			sb.append('"');
		}
		if (append_exprs != null && append_exprs.size() > 0) {
			if (sb.length() > 0) {
				args.add(new Ast.StringLiteral(sb.toString()));
				sb.setLength(0);
			}
			args.addAll(append_exprs);
		}
		if ((s = t.getExtraSpace()) != null) sb.append(s);
		if (t.isEmpty()) sb.append('/');
		sb.append('>');
		if ((s = t.getTailSpace()) != null) sb.append(s);
		args.add(new Ast.StringLiteral(sb.toString()));
		return args;
	}

	
	public Ast.PrintStatement buildPrintStatement(TagInfo taginfo, AttrInfo attr_info, List append_exprs) {
		List args = buildPrintStatementArguments(taginfo, attr_info, append_exprs);
		return new Ast.PrintStatement(args);
	}
	
	public Ast.PrintStatement buildPrintStatementForExpression(Ast.Expression expr, TagInfo stag_info, TagInfo etag_info) {
		String head_space = (stag_info != null ? stag_info : etag_info).getHeadSpace();
		String tail_space = (etag_info != null ? etag_info : stag_info).getTailSpace();
		List args = new ArrayList();
		if (head_space != null && head_space.length() > 0)
			args.add(new Ast.StringLiteral(head_space));
		args.add(expr);
		if (tail_space != null && tail_space.length() > 0)
			args.add(new Ast.StringLiteral(tail_space));
		return new Ast.PrintStatement(args);
	}
	
	public Ast.PrintStatement stagStatement(ElementInfo elem_info) {
		return buildPrintStatement(elem_info.getStagInfo(), elem_info.getAttrInfo(), elem_info.getAppendExprs());
	}
	
	public Ast.PrintStatement etagStatement(ElementInfo elem_info) {
		return buildPrintStatement(elem_info.getEtagInfo(), null, null);
	}
	
	////
	public Ast.Expression parseAndEscapeExpression(String directive_name, String directive_arg, int linenum) throws ConvertException {
		Ast.Expression expr = null;
		try {
			Parser parser = new ExpressionParser();
			expr = (Ast.Expression)parser.parse(directive_arg);
		}
		catch (ParseException ex) {
			throw convertError(ex.getMessage(), linenum);
		}
		int escape_flag = Parser.detectEscapeFlag(directive_name);
		if (escape_flag != 0) {
			String funcname = escape_flag > 0 ? "E" : "X";
			expr = (Ast.Expression)Ast.Helper.wrapWithFunction(expr, funcname);
		}
		return expr;
	}
	
	
	////
	private static HandlerHelper __instance = new HandlerHelper();
	
	public static HandlerHelper getInstance() {
		return __instance;
	}
	
}



public class BaseHandler implements Handler {
	List    _rulesets;
	Map     _ruleset_table = new HashMap();
	Map     _elem_info_table = new HashMap();
	boolean _delspan = false;
	String  _dattr = "kw:d";
	HandlerHelper _helper = HandlerHelper.getInstance();

	public BaseHandler(List rulesets, Map properties) {
		_rulesets = rulesets;
		for (Iterator it = rulesets.iterator(); it.hasNext(); ) {
			Ast.Ruleset ruleset = (Ast.Ruleset)it.next();
			String[] selector_names = ruleset.selectorNames();
			for (int i = 0, n = selector_names.length; i < n; i++) {
				_registerRuleset(selector_names[i], ruleset);
			}
		}
		if (properties != null) {
			Object val = properties.get("delspan");
			_delspan = val == Boolean.TRUE;
			val = properties.get("dattr");
			if (val != null && val instanceof String)
				_dattr = (String)val;
		}
	}
	
	public BaseHandler(List rulesets) {
		this(rulesets, null);
	}

	private void _registerRuleset(String key, Ast.Ruleset ruleset) {
		Ast.Ruleset ruleset2 = (Ast.Ruleset)_ruleset_table.get(key);
		if (ruleset2 != null)
			ruleset = Ast.Ruleset.merged(ruleset2, ruleset);
		_ruleset_table.put(key, ruleset);
	}
	
	public Ast.Ruleset getRuleset(String selector_name) {
		return (Ast.Ruleset)_ruleset_table.get(selector_name);
	}

	
	public boolean hasDirective(AttrInfo attr_info, TagInfo tag_info) throws ConvertException {
		// kw:d attribute
		String dattr_name = _dattr;         // ex. _dattr == 'kw:d'
		String dattr_value = (String)attr_info.getValue(dattr_name); 
		if (dattr_value != null && dattr_value.length() > 0) {
			if (dattr_value.charAt(0) == ' ') {
				String value = dattr_value.substring(1, dattr_value.length()-1);
				String space = null;
				attr_info.set(dattr_name, value, space);
				dattr_value = null;
			}
			else {
				if (! Pattern.matches("\\A\\w+:.*", dattr_value))
					throw _helper.convertError("'"+dattr_name+"=\""+dattr_value+"\"': invalid directive pattern.", tag_info.getLinenum());
				attr_info.remove(dattr_name);
			}
			tag_info.rebuildTagText(attr_info);
		}
		if (dattr_value == null) {
			dattr_name = "id";
			dattr_value = (String)attr_info.getValue(dattr_name);
			if (dattr_value != null) {
				if (Pattern.matches("\\A\\w+\\z", dattr_value)) {
					dattr_value = "mark:" + dattr_value;
				}
				else if (Pattern.matches("\\A\\w+:.*", dattr_value)) {
					attr_info.remove("id");
					tag_info.rebuildTagText(attr_info);
				}
				else {
					dattr_value = null;
				}
			}	
		}
		if (dattr_value == null)
			return false;
		tag_info.setDirective(dattr_name, dattr_value);
		return true;
	}

	
	
	private static Pattern __directive_pattern = Pattern.compile("\\A(\\w+):\\s*(.*)");

	
	public void handleDirectives(ElementInfo elem_info, List stmt_list) throws ConvertException {
		
		String directive_name = null, directive_arg = null, directive_str = null;
		_findAndMergeRuleset(elem_info, true, true, false);
		
		/// handle 'attr:' and 'append:' directives
		String d_str = null;
		TagInfo stag_info = elem_info.getStagInfo(), etag_info = elem_info.getEtagInfo();
		if (stag_info.getDirectiveStr() != null) {
			String[] strs = stag_info.getDirectiveStr().split(";");
			Pattern pattern = __directive_pattern;   //  /\A(\w+):\s*(.*)/
			for (int i = 0, n = strs.length; i < n; i++) {
				d_str = strs[i].trim();
				Matcher m = pattern.matcher(d_str);
				if (! m.find()) {
					throw _helper.convertError("'"+d_str+"': invalid directive pattern.", stag_info.getLinenum());
				}
				String d_name = m.group(1);   // directive_name
				String d_arg  = m.group(2);   // directive_arg
				Integer d_kind_obj = (Integer)__directive_table.get(d_name);
				int d_kind = d_kind_obj != null ? d_kind_obj.intValue() : -1;
				if (d_kind == D_ATTR || d_kind == D_APPEND) {
					handle(d_name, d_arg, d_str, elem_info, stmt_list);
				}
				else {
					if (directive_name != null)
						throw _helper.convertError("'"+d_str+"': not available with '"+directive_name+"' directive.", stag_info.getLinenum());
					directive_name = d_name;
					directive_arg  = d_arg;
					directive_str  = d_str;
				}
			}//for
		}//if

		/// remove dummy <span> tag
		if (_delspan && "span".equals(stag_info.getTagName()) && elem_info.getAttrInfo().isEmpty()
				     && elem_info.getAppendExprs().isEmpty() && !"id".equals(directive_name)) {
			stag_info.setTagName(null);
			etag_info.setTagName(null);
		}
		
		/// handle other directives
		boolean result = handle(directive_name, directive_arg, directive_str, elem_info, stmt_list);
		if (directive_name != null && !result) {
			throw _helper.convertError("'"+directive_str+"': unknown directive.", stag_info.getLinenum());
		}
	}

	

	public boolean handle(String directive_name, String directive_arg, String directive_str, ElementInfo elem_info, List stmt_list) throws ConvertException {
		
		String d_name = directive_name;
		String d_arg = directive_arg;
		String d_str = directive_str;
		//
		TagInfo  stag_info    = elem_info.getStagInfo();
		TagInfo  etag_info    = elem_info.getEtagInfo();
		List     cont_stmts   = elem_info.getContStmts();
		AttrInfo attr_info    = elem_info.getAttrInfo();
		List     append_exprs = elem_info.getAppendExprs();
		int stag_linenum = stag_info.getLinenum();
		//
		if (d_name == null) {
			assert !attr_info.isEmpty() || !append_exprs.isEmpty();  // ???
			stmt_list.add(_helper.stagStatement(elem_info));
			stmt_list.addAll(cont_stmts);
			if (etag_info != null)
				stmt_list.add(_helper.etagStatement(elem_info));   // when not empty-tag
			return true;
		}
		Integer d_kind_obj = (Integer)__directive_table.get(d_name);
		if (d_kind_obj == null)
			return false;
		Ast.Expression expr;
		int d_kind = d_kind_obj.intValue();
		switch (d_kind) {
		case D_DUMMY:
			// nothing
			return true;
		case D_ID:
		case D_MARK:
			if (! Pattern.matches("\\A\\w+\\z", d_arg))
				throw _helper.convertError("'"+d_str+"': invalid marking name.", stag_linenum);
			String name = d_arg;
			Ast.Ruleset ruleset = (Ast.Ruleset)_ruleset_table.get("#"+name); 
			if (ruleset != null)
				elem_info.merge(ruleset);
			if (_elem_info_table.containsKey(name)) {
				int previous_linenum = ((ElementInfo)_elem_info_table.get(name)).getStagInfo().getLinenum();
				String msg = "'"+d_str+"': id '"+name+"' is already used at line "+previous_linenum+".";
				throw _helper.convertError(msg, stag_linenum);
			}
			_elem_info_table.put(name, elem_info);
			boolean content_only = false;
			Ast.Statement stmt = expandElementInfo(elem_info, content_only);
			assert stmt != null;
			assert stmt.getToken() == Token.BLOCK;
			_addBlockStatement(stmt_list, (Ast.BlockStatement)stmt);
			return true;
		case D_STAG:
			_helper.errorIfEmptyTag(elem_info, directive_str);					
			expr = _helper.parseAndEscapeExpression(d_name, d_arg, stag_linenum);
			stmt_list.add(_helper.buildPrintStatementForExpression(expr, stag_info, etag_info));
			stmt_list.addAll(cont_stmts);
			stmt_list.add(_helper.etagStatement(elem_info));
			return true;
		case D_ETAG:
			_helper.errorIfEmptyTag(elem_info, directive_str);
			expr = _helper.parseAndEscapeExpression(d_name, d_arg, stag_linenum);
			stmt_list.add(_helper.stagStatement(elem_info));
			stmt_list.addAll(cont_stmts);
			stmt_list.add(_helper.buildPrintStatementForExpression(expr, stag_info, etag_info));
			return true;
		case D_CONT:
		case D_VALUE:
			_helper.errorIfEmptyTag(elem_info, directive_str);
			stag_info.setTailSpace("");
			etag_info.setHeadSpace("");
			List pargs = _helper.buildPrintStatementArguments(stag_info, attr_info, append_exprs);
			expr = _helper.parseAndEscapeExpression(d_name, d_arg, stag_linenum);
			pargs.add(expr);
			if (etag_info.getTagName() != null)
				pargs.add(etag_info.getTagText());
			stmt_list.add(new Ast.PrintStatement(pargs));
			return true;
		case D_ELEM:
			expr = _helper.parseAndEscapeExpression(d_name, d_arg, stag_linenum);
			stmt_list.add(_helper.buildPrintStatementForExpression(expr, stag_info, etag_info));
			return true;
		case D_ATTR:
			Pattern pattern = Pattern.compile("\\A(\\w+(?::\\w+)?)[:=](.*)\\z");
			Matcher m = pattern.matcher(d_arg);
			if (! m.find())
				throw _helper.convertError("'"+d_str+"': invalid attr pattern.", stag_linenum);
			String aname = m.group(1), avalue = m.group(2);
			expr = _helper.parseAndEscapeExpression(d_name, avalue, stag_linenum);
			attr_info.set(aname, expr, null);
			return true;
		case D_APPEND:
			expr = _helper.parseAndEscapeExpression(d_name, d_arg, stag_linenum);
			append_exprs.add(expr);
		case D_REPLACE1:
		case D_REPLACE2:
		case D_REPLACE3:
		case D_REPLACE4:
			// TBC
			return true;
		}
		return false;
	}
	
	private static final int D_DUMMY    = 0;
	private static final int D_ID       = 1;
	private static final int D_MARK     = 2;
	private static final int D_STAG     = 3;
	private static final int D_CONT     = 4;
	private static final int D_ETAG     = 5;
	private static final int D_ELEM     = 6;
	private static final int D_VALUE    = 7;
	private static final int D_ATTR     = 8;
	private static final int D_APPEND   = 9;
	private static final int D_REPLACE1 = 10;
	private static final int D_REPLACE2 = 11;
	private static final int D_REPLACE3 = 12;
	private static final int D_REPLACE4 = 13;
	
	private static final HashMap __directive_table;
	static {
		__directive_table = new HashMap();
		__directive_table.put("dummy",  new Integer(D_DUMMY));
		__directive_table.put("id",     new Integer(D_ID));
		__directive_table.put("mark",   new Integer(D_MARK));
		__directive_table.put("stag",   new Integer(D_STAG));
		__directive_table.put("Stag",   new Integer(D_STAG));
		__directive_table.put("STAG",   new Integer(D_STAG));
		__directive_table.put("cont",   new Integer(D_CONT));
		__directive_table.put("Cont",   new Integer(D_CONT));
		__directive_table.put("CONT",   new Integer(D_CONT));
		__directive_table.put("etag",   new Integer(D_ETAG));
		__directive_table.put("Etag",   new Integer(D_ETAG));
		__directive_table.put("ETAG",   new Integer(D_ETAG));
		__directive_table.put("elem",   new Integer(D_ELEM));
		__directive_table.put("Elem",   new Integer(D_ELEM));
		__directive_table.put("ELEM",   new Integer(D_ELEM));
		__directive_table.put("value",  new Integer(D_VALUE));
		__directive_table.put("Value",  new Integer(D_VALUE));
		__directive_table.put("VALUE",  new Integer(D_VALUE));
		__directive_table.put("attr",   new Integer(D_ATTR));
		__directive_table.put("Attr",   new Integer(D_ATTR));
		__directive_table.put("ATTR",   new Integer(D_ATTR));
		__directive_table.put("append", new Integer(D_APPEND));
		__directive_table.put("Append", new Integer(D_APPEND));
		__directive_table.put("APPEND", new Integer(D_APPEND));
		__directive_table.put("replace_element_with_element", new Integer(D_REPLACE1));
		__directive_table.put("replace_element_with_content", new Integer(D_REPLACE2));
		__directive_table.put("replace_content_with_element", new Integer(D_REPLACE3));
		__directive_table.put("replace_content_with_content", new Integer(D_REPLACE4));
	}


	private void _addBlockStatement(List stmt_list, Ast.BlockStatement block_stmt) {
		Ast.Statement[] stmts = block_stmt.getStatements();
		for (int i = 0, n = stmts.length; i < n; i++)
			stmt_list.add(stmts[i]);
	}
	
	
//	private void _addStatement(List stmt_list, Ast.Statement stmt) {
//		//System.err.println("*** debug: _addStatement(): stmt.getToken()="+stmt.getToken());
//		if (stmt.getToken() == Token.BLOCK)
//			_addBlockStatement(stmt_list, (Ast.BlockStatement)stmt);
//		else
//			stmt_list.add(stmt);
//	}
	


	private void _findAndMergeRuleset(ElementInfo elem_info, boolean flag_tagname, boolean flag_classname, boolean flag_idname) {
		Ast.Ruleset ruleset;
		TagInfo stag_info = elem_info.getStagInfo();
		if (flag_tagname) {
			String tagname = stag_info.getTagName();
			if ((ruleset = (Ast.Ruleset)_ruleset_table.get(tagname)) != null)
				elem_info.merge(ruleset);
		}
		AttrInfo attr_info = elem_info.getAttrInfo();
		if (flag_classname) {
			String classname = (String)attr_info.getValue("class");
			if (classname != null && (ruleset = (Ast.Ruleset)_ruleset_table.get("."+classname)) != null)
				elem_info.merge(ruleset);
		}
		if (flag_idname) {
			String idname = (String)attr_info.getValue("id");
			if (idname != null && (ruleset = (Ast.Ruleset)_ruleset_table.get("#"+idname)) != null)
				elem_info.merge(ruleset);
		}
	}


	public void applyRuleset(ElementInfo elem_info, List stmt_list) throws ConvertException {
		_findAndMergeRuleset(elem_info, true, true, true);
		Ast.Statement stmt = expandElementInfo(elem_info, false);
		assert stmt != null;
		//stmt_list.add(stmt);
		assert stmt.getToken() == Token.BLOCK;
		_addBlockStatement(stmt_list, (Ast.BlockStatement)stmt);
	}

	
	public void applyRuleset(TagInfo stag_info, TagInfo etag_info, List cont_stmts, AttrInfo attr_info, List stmt_list) throws ConvertException  {
		String name = null;
		List append_exprs = null;
		ElementInfo elem_info = new ElementInfo(name, stag_info, etag_info, cont_stmts, attr_info, append_exprs); 
		Ast.Ruleset ruleset;
		String tagname = stag_info.getTagName();
		if ((ruleset = (Ast.Ruleset)_ruleset_table.get(tagname)) != null)
			elem_info.merge(ruleset);
		String classname = (String)attr_info.getValue("class");
		if (classname != null && (ruleset = (Ast.Ruleset)_ruleset_table.get("."+classname)) != null)
			elem_info.merge(ruleset);
		String idname = (String)attr_info.getValue("id");
		if (idname != null && (ruleset = (Ast.Ruleset)_ruleset_table.get("#"+idname)) != null)
			elem_info.merge(ruleset);
		Ast.Statement stmt = expandElementInfo(elem_info, false);
		assert stmt != null;
		//stmt_list.add(stmt);
		assert stmt.getToken() == Token.BLOCK;
		_addBlockStatement(stmt_list, (Ast.BlockStatement)stmt);
	}
	
	
	public Ast.Statement expandElementInfo(ElementInfo elem_info, boolean content_only) throws ConvertException {
//		String name = elem_info.getName();
//		if (name != null) {
//			Ast.Ruleset ruleset = (Ast.Ruleset)_ruleset_table.get("#"+name);
//			if (ruleset != null && !elem_info.isMerged())
//				elem_info.merge(ruleset);
//		}
		Ast.Statement stmt;
		if (content_only) {
			Ast.Statement cont_stmt = new Ast.ContStatement();
			stmt = expandStatement(cont_stmt, elem_info);
		}
		else {
			List stmt_list = new ArrayList();
			if (elem_info.getElemExpr() != null) {
				stmt = _helper.buildPrintStatementForExpression(elem_info.getElemExpr(), elem_info.getStagInfo(), elem_info.getEtagInfo());
				stmt_list.add(stmt);
			}
			else {
				for (Iterator it = elem_info.getLogic().iterator(); it.hasNext(); ) {
					stmt = (Ast.Statement)it.next();
					Ast.Statement stmt2 = expandStatement(stmt, elem_info);
					stmt_list.add(stmt2 != null ? stmt2 : stmt);
					//_addStatement(stmt_list, stmt2 != null ? stmt2 : stmt);
				}
			}
			stmt = new Ast.BlockStatement(stmt_list);
		}
		return stmt;
	}
	
	
	
	/**
	 * expand _stag, _cont, _etag, _elem, _element(), and _content().
	 * 
	 * @return Statement if stmt is one of the _stag, _cont, _etag, _elem, _elemen(), or _content(). Otherwise, Null. 
	 */
	public Ast.Statement expandStatement(Ast.Statement stmt, ElementInfo elem_info) throws ConvertException {
		// delete dummy <span> tag
		ElementInfo e = elem_info;
		if (_delspan && e.getStagInfo().getTagName().equals("span") && e.getAttrInfo().isEmpty() && e.getAppendExprs().isEmpty()) {
			e.getStagInfo().setTagName(null);
			e.getEtagInfo().setTagName(null);
		}
		//
		int t = stmt.getToken();
		switch (t) {
		case Token.PRINT:
			return null;
		case Token.EXPR:
			return null;
		case Token.IF:
			Ast.IfStatement if_stmt = (Ast.IfStatement)stmt; 
			expandStatement(if_stmt.getThenStatement(), elem_info);
			if (if_stmt.getElseStatement() != null)
				expandStatement(if_stmt.getElseStatement(), elem_info);
			return null;
		case Token.ELSEIF:
		case Token.ELSE:
			assert false; /* unreachable */
			return null;
		case Token.WHILE:
			expandStatement(((Ast.WhileStatement)stmt).getBodyStatement(), elem_info);
			return null;
		case Token.FOREACH:
			expandStatement(((Ast.ForeachStatement)stmt).getBodyStatement(), elem_info);
			return null;
		case Token.BREAK:
			return null;
		case Token.CONTINUE:
			return null;
		case Token.BLOCK:
			Ast.Statement[] stmts = ((Ast.BlockStatement)stmt).getStatements();
			for (int i = 0, n = stmts.length; i < n; i++) {
				Ast.Statement st = expandStatement(stmts[i], elem_info);
				if (st != null) stmts[i] = st;
			}
			return null;
		case Token.STAG:
			assert elem_info != null;
			return _expandStag(elem_info);
		case Token.CONT:
			assert elem_info != null;
			return _expandCont(elem_info);
		case Token.ETAG:
			assert elem_info != null;
			return _expandEtag(elem_info);
		case Token.ELEM:
			if (e.getElemExpr() != null) {
				return _helper.buildPrintStatementForExpression(e.getElemExpr(), e.getStagInfo(), e.getEtagInfo());
			}
			else {
				List list = new ArrayList();
				Ast.Statement st;
				st = _expandStag(elem_info);  if (st != null) list.add(st);
				st = _expandCont(elem_info);  if (st != null) list.add(st);  //_addStatement(list, st);
				st = _expandEtag(elem_info);  if (st != null) list.add(st);
				return new Ast.BlockStatement(list);
			}
		case Token.ELEMENT:
		case Token.CONTENT:
			String name = ((Ast.ExpandStatement)stmt).getName();
			ElementInfo elem_info2 = (ElementInfo)_elem_info_table.get(name);
			if (elem_info2 == null)
				throw _helper.convertError("element '"+name+"' is not found.", stmt.getLinenum());
			boolean content_only = t == Token.CONTENT;
			return expandElementInfo(elem_info2, content_only);
		default:
			assert false;
		}
		return null;	
	}

	private Ast.Statement _expandStag(ElementInfo e) {
		if (e.getStagExpr() != null)
			return _helper.buildPrintStatementForExpression(e.getStagExpr(), e.getStagInfo(), null);
		else
			return _helper.buildPrintStatement(e.getStagInfo(), e.getAttrInfo(), e.getAppendExprs());
	}

	private Ast.Statement _expandEtag(ElementInfo e) {
		if (e.getEtagExpr() != null)
			return _helper.buildPrintStatementForExpression(e.getEtagExpr(), null, e.getEtagInfo());
		else if (e.getEtagInfo() == null)  // e.getEtagInfo() is null when <br>, <input>, <hr>, <img>, and <meta>
			return _helper.createTextPrintStatement("");
		else
			return _helper.buildPrintStatement(e.getEtagInfo(), null, null);
	}
	
	private Ast.Statement _expandCont(ElementInfo e) throws ConvertException {
		if (e.getContExpr() != null) {
			return new Ast.PrintStatement(new Ast.Expression[] { e.getContExpr() });
		}
		else {
			Ast.BlockStatement block_stmt = new Ast.BlockStatement(e.getContStmts());
			expandStatement(block_stmt, e);
			return block_stmt;
		}
	}
	
	
	public Ast.Statement extract(String elem_name, boolean content_only) throws ConvertException {
		ElementInfo elem_info = (ElementInfo)_elem_info_table.get(elem_name);
		if (elem_info == null) {
			throw _helper.convertError("element '"+elem_name+"' not found.", elem_info.getStagInfo().getLinenum());
		}
		return expandElementInfo(elem_info, content_only);
	}


	
	public static void main(String[] args) throws Exception {
		String plogic = ""
			+ "#list {\n"
			+ "  logic: {\n"
			+ "    foreach (item = list) {\n"
			+ "      _stag;\n"
			+ "      _cont;\n"
			+ "      _etag;\n"
			+ "    }\n"
			+ "  }\n"
			+ "}\n"
			+ "#item { value: item; }\n"
			;
		String pdata = ""
			+ "<table>\n"
			+ " <tr kw:d=\"value:list\">\n"
			+ "  <td id=\"mark:item\">foo</td>\n"
			+ " </tr>\n"
			+ "</table>\n"
			;
		Parser parser = new PresentationLogicParser();
		List rulesets = (List)parser.parse(plogic);
//		for (Iterator it = rulesets.iterator(); it.hasNext(); ) {
//			Ast.Ruleset ruleset = (Ast.Ruleset)it.next();
//			System.out.println(ruleset.inspect());
//		}
		Handler handler = new BaseHandler(rulesets, null);
		TextConverter converter = new TextConverter(handler);
		converter._reset(pdata, 1);
		TagInfo tag_info;
		List list = new ArrayList();
		while ((tag_info = converter._fetch()) != null) {
			list.add(tag_info);
		}
		TagInfo stag_info = (TagInfo)list.get(1), etag_info = (TagInfo)list.get(3);
		AttrInfo attr_info = new AttrInfo(stag_info.getAttrStr());
		List cont_stmts = new ArrayList();
		cont_stmts.add(new Ast.PrintStatement(new Ast.Expression[] { new Ast.StringLiteral("hoge")}));
		handler.hasDirective(attr_info, stag_info);
		String d_name = "mark", d_arg = "list", d_str="mark:list";
		List append_exprs = new ArrayList();
		ElementInfo elem_info = new ElementInfo(null, stag_info, etag_info, cont_stmts, attr_info, append_exprs);
		List stmt_list = new ArrayList();
		handler.handle(d_name, d_arg, d_str, elem_info, stmt_list);
		for (int i = 0, n = stmt_list.size(); i < n; i++) {
			System.out.println(((Ast.Statement)stmt_list.get(i)).inspect());
		}
		//
		//System.out.println(tag_info._inspect());
		//System.out.println(Util.inspect(attr_info.getNames()));
		//System.out.println("result="+result);
	}


}
