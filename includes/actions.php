<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD']==='POST' && !in_array(($_POST['action'] ?? ''), ['login','register'], true)) {
  requireCsrf();
  $action=(string)($_POST['action'] ?? '');
  try {
    if ($action==='logout') { $_SESSION=[]; session_destroy(); header('Location: index.php'); exit; }
    elseif ($action==='save_profile') { $name=trim((string)($_POST['display_name']??'')); if(mb_strlen($name)<2||mb_strlen($name)>100) throw new RuntimeException('نام نمایشی باید بین ۲ تا ۱۰۰ نویسه باشد.'); $stmt=$pdo->prepare('UPDATE users SET display_name=? WHERE id=?'); $stmt->execute([$name,$userId]); }
    elseif ($action==='admin_toggle_user') { if(($_SESSION['role']??'')!=='admin') throw new RuntimeException('دسترسی مجاز نیست.'); $target=(int)($_POST['id']??0); if($target===$userId) throw new RuntimeException('نمی‌توانی حساب خودت را غیرفعال کنی.'); $stmt=$pdo->prepare('UPDATE users SET is_active=1-is_active WHERE id=?'); $stmt->execute([$target]); }
    elseif ($action==='admin_change_role') { if(($_SESSION['role']??'')!=='admin') throw new RuntimeException('دسترسی مجاز نیست.'); $target=(int)($_POST['id']??0); $role=($_POST['role']??'')==='admin'?'admin':'user'; if($target===$userId&&$role!=='admin') throw new RuntimeException('نمی‌توانی دسترسی مدیریت خودت را حذف کنی.'); $stmt=$pdo->prepare('UPDATE users SET role=? WHERE id=?'); $stmt->execute([$role,$target]); }
    elseif ($action==='add_category') {
      $name=trim((string)($_POST['name']??''));if(mb_strlen($name)<2||mb_strlen($name)>80)throw new RuntimeException('نام دسته‌بندی باید بین ۲ تا ۸۰ نویسه باشد.');$stmt=$pdo->prepare('INSERT INTO categories(user_id,name) VALUES(?,?)');try{$stmt->execute([$userId,$name]);}catch(PDOException $e){if($e->getCode()==='23000')throw new RuntimeException('این دسته‌بندی از قبل وجود دارد.');throw $e;}
    } elseif ($action==='rename_category') {
      $id=(int)($_POST['id']??0);$name=trim((string)($_POST['name']??''));if($id<1||mb_strlen($name)<2||mb_strlen($name)>80)throw new RuntimeException('نام دسته‌بندی معتبر نیست.');$stmt=$pdo->prepare('SELECT name FROM categories WHERE id=? AND user_id=?');$stmt->execute([$id,$userId]);$oldName=$stmt->fetchColumn();if($oldName===false)throw new RuntimeException('دسته‌بندی پیدا نشد.');$check=$pdo->prepare('SELECT id FROM categories WHERE user_id=? AND name=? AND id<>?');$check->execute([$userId,$name,$id]);if($check->fetchColumn())throw new RuntimeException('دسته‌ای با این نام وجود دارد.');$pdo->beginTransaction();try{$pdo->prepare('UPDATE transactions SET category=? WHERE user_id=? AND category=?')->execute([$name,$userId,$oldName]);$pdo->prepare('UPDATE budgets SET category=? WHERE user_id=? AND category=?')->execute([$name,$userId,$oldName]);$pdo->prepare('UPDATE categories SET name=? WHERE id=? AND user_id=?')->execute([$name,$id,$userId]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    } elseif ($action==='delete_category') {
      $id=(int)($_POST['id']??0);$stmt=$pdo->prepare('SELECT name FROM categories WHERE id=? AND user_id=?');$stmt->execute([$id,$userId]);$name=$stmt->fetchColumn();if($name===false)throw new RuntimeException('دسته‌بندی پیدا نشد.');$check=$pdo->prepare("SELECT (SELECT COUNT(*) FROM transactions WHERE user_id=? AND category=?)+(SELECT COUNT(*) FROM budgets WHERE user_id=? AND category=?)");$check->execute([$userId,$name,$userId,$name]);if((int)$check->fetchColumn()>0)throw new RuntimeException('این دسته به تراکنش یا بودجه متصل است؛ برای حفظ سوابق، ابتدا تراکنش‌ها/بودجه را به دستهٔ دیگری منتقل کن.');$pdo->prepare('DELETE FROM categories WHERE id=? AND user_id=?')->execute([$id,$userId]);
    } elseif ($action==='add_transaction') {
      $type=($_POST['type'] ?? '')==='income'?'income':'expense';
      $amount=filter_var(latinDigits((string)($_POST['amount'] ?? '')),FILTER_VALIDATE_INT);
      $category=trim((string)($_POST['category'] ?? 'متفرقه'));
      $note=trim((string)($_POST['note'] ?? ''));
      $date=parseJalaliDate((string)($_POST['transaction_date'] ?? jalaliDate(date('Y-m-d'))));
      if (!$amount || $amount<1 || $amount>999999999999 || !$date) throw new RuntimeException('مبلغ یا تاریخ شمسی معتبر نیست.');
      if ($category==='') $category='متفرقه';$validCategory=$pdo->prepare('SELECT id FROM categories WHERE user_id=? AND name=?');$validCategory->execute([$userId,$category]);if(!$validCategory->fetchColumn())throw new RuntimeException('دسته‌بندی انتخاب‌شده معتبر نیست.');
      $stmt=$pdo->prepare('INSERT INTO transactions(user_id,type,amount,category,note,transaction_date) VALUES(?,?,?,?,?,?)');
      $stmt->execute([$userId,$type,$amount,mb_substr($category,0,80),mb_substr($note,0,255),$date]);
    } elseif ($action==='delete_transaction') {
      $stmt=$pdo->prepare('DELETE FROM transactions WHERE id=? AND user_id=?'); $stmt->execute([(int)($_POST['id']??0),$userId]);
    } elseif ($action==='update_transaction') {
      $id=(int)($_POST['id']??0);$type=($_POST['type']??'')==='income'?'income':'expense';$amount=filter_var(latinDigits((string)($_POST['amount']??'')),FILTER_VALIDATE_INT);$category=trim((string)($_POST['category']??'متفرقه'));$note=trim((string)($_POST['note']??''));$date=parseJalaliDate((string)($_POST['transaction_date']??''));
      if($id<1||!$amount||$amount<1||$amount>999999999999||!$date)throw new RuntimeException('مبلغ یا تاریخ شمسی معتبر نیست.');if($category==='')$category='متفرقه';$validCategory=$pdo->prepare('SELECT id FROM categories WHERE user_id=? AND name=?');$validCategory->execute([$userId,$category]);if(!$validCategory->fetchColumn())throw new RuntimeException('دسته‌بندی انتخاب‌شده معتبر نیست.');$stmt=$pdo->prepare('UPDATE transactions SET type=?,amount=?,category=?,note=?,transaction_date=? WHERE id=? AND user_id=?');$stmt->execute([$type,$amount,mb_substr($category,0,80),mb_substr($note,0,255),$date,$id,$userId]);
    } elseif ($action==='add_task') {
      $title=trim((string)($_POST['title']??'')); $dueInput=trim((string)($_POST['due_date']??'')); $due=$dueInput===''?'':parseJalaliDate($dueInput); $priority=in_array($_POST['priority']??'', ['low','medium','high'],true)?$_POST['priority']:'medium';
      if ($title==='') throw new RuntimeException('عنوان کار را وارد کن.');
      if ($dueInput!=='' && $due===null) throw new RuntimeException('تاریخ سررسید شمسی معتبر نیست.');
      $stmt=$pdo->prepare('INSERT INTO tasks(user_id,title,due_date,priority) VALUES(?,?,?,?)'); $stmt->execute([$userId,mb_substr($title,0,180),$due?:null,$priority]);
    } elseif ($action==='update_task') {
      $id=(int)($_POST['id']??0);$title=trim((string)($_POST['title']??''));$dueInput=trim((string)($_POST['due_date']??''));$due=$dueInput===''?null:parseJalaliDate($dueInput);$priority=in_array($_POST['priority']??'', ['low','medium','high'],true)?$_POST['priority']:'medium';
      if($id<1||$title==='')throw new RuntimeException('عنوان کار را وارد کن.');if($dueInput!==''&&$due===null)throw new RuntimeException('تاریخ سررسید شمسی معتبر نیست؛ از قالب سال/ماه/روز مثل ۱۴۰۵/۰۸/۰۷ استفاده کن.');$stmt=$pdo->prepare('UPDATE tasks SET title=?,due_date=?,priority=? WHERE id=? AND user_id=?');$stmt->execute([mb_substr($title,0,180),$due,$priority,$id,$userId]);if($stmt->rowCount()===0){$check=$pdo->prepare('SELECT id FROM tasks WHERE id=? AND user_id=?');$check->execute([$id,$userId]);if(!$check->fetchColumn())throw new RuntimeException('این کار پیدا نشد یا متعلق به حساب تو نیست.');}
    } elseif ($action==='toggle_task') {
      $stmt=$pdo->prepare('UPDATE tasks SET is_done=1-is_done WHERE id=? AND user_id=?'); $stmt->execute([(int)($_POST['id']??0),$userId]);
    } elseif ($action==='delete_task') {
      $stmt=$pdo->prepare('DELETE FROM tasks WHERE id=? AND user_id=?'); $stmt->execute([(int)($_POST['id']??0),$userId]);
    } elseif ($action==='save_check') {
      $id=(int)($_POST['id']??0);$number=trim((string)($_POST['check_number']??''));$bank=trim((string)($_POST['bank_name']??''));$type=($_POST['check_type']??'')==='received'?'received':'issued';$amount=filter_var(latinDigits((string)($_POST['amount']??'')),FILTER_VALIDATE_INT);$due=parseJalaliDate((string)($_POST['due_date']??''));$payee=trim((string)($_POST['payee']??''));$status=in_array($_POST['status']??'', ['pending','cleared','cancelled','bounced'],true)?$_POST['status']:'pending';$notes=trim((string)($_POST['notes']??''));
      if(!$amount||$amount<1||$amount>999999999999||!$due||mb_strlen($number)>80||mb_strlen($bank)>100||mb_strlen($payee)>120||mb_strlen($notes)>500)throw new RuntimeException('مبلغ، تاریخ سررسید یا اطلاعات چک معتبر نیست.');
      if($id>0){$stmt=$pdo->prepare('UPDATE checks SET check_number=?,bank_name=?,check_type=?,amount=?,due_date=?,payee=?,status=?,notes=? WHERE id=? AND user_id=?');$stmt->execute([$number,$bank,$type,$amount,$due,$payee,$status,$notes,$id,$userId]);}
      else{$stmt=$pdo->prepare('INSERT INTO checks(user_id,check_number,bank_name,check_type,amount,due_date,payee,status,notes) VALUES(?,?,?,?,?,?,?,?,?)');$stmt->execute([$userId,$number,$bank,$type,$amount,$due,$payee,$status,$notes]);}
    } elseif ($action==='delete_check') {
      $stmt=$pdo->prepare('DELETE FROM checks WHERE id=? AND user_id=?');$stmt->execute([(int)($_POST['id']??0),$userId]);
    } elseif ($action==='save_contact') {
      $id=(int)($_POST['id']??0);$name=trim((string)($_POST['name']??''));$phone=trim((string)($_POST['phone']??''));$email=trim((string)($_POST['email']??''));$company=trim((string)($_POST['company']??''));$notes=trim((string)($_POST['notes']??''));
      if(mb_strlen($name)<2||mb_strlen($name)>120)throw new RuntimeException('نام مخاطب باید بین ۲ تا ۱۲۰ نویسه باشد.');if(mb_strlen($phone)>30||mb_strlen($email)>190||mb_strlen($company)>120||mb_strlen($notes)>500)throw new RuntimeException('یکی از فیلدها بیش از حد طولانی است.');if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('ایمیل معتبر وارد کن.');
      if($id>0){$stmt=$pdo->prepare('UPDATE contacts SET name=?,phone=?,email=?,company=?,notes=? WHERE id=? AND user_id=?');$stmt->execute([$name,$phone,$email,$company,$notes,$id,$userId]);}else{$stmt=$pdo->prepare('INSERT INTO contacts(user_id,name,phone,email,company,notes) VALUES(?,?,?,?,?,?)');$stmt->execute([$userId,$name,$phone,$email,$company,$notes]);}
    } elseif ($action==='delete_contact') {
      $stmt=$pdo->prepare('DELETE FROM contacts WHERE id=? AND user_id=?');$stmt->execute([(int)($_POST['id']??0),$userId]);
    } elseif ($action==='save_card') {
      $id=(int)($_POST['id']??0);$bank=trim((string)($_POST['bank_name']??''));$label=trim((string)($_POST['card_label']??''));$holder=trim((string)($_POST['holder_name']??''));$last4=latinDigits(trim((string)($_POST['last4']??'')));
      if(mb_strlen($bank)<2||mb_strlen($bank)>100||!preg_match('/^[0-9]{4}$/',$last4)||mb_strlen($label)>80||mb_strlen($holder)>120)throw new RuntimeException('نام بانک و چهار رقم پایانی کارت را درست وارد کن.');
      if($id>0){$stmt=$pdo->prepare('UPDATE bank_cards SET bank_name=?,card_label=?,holder_name=?,last4=? WHERE id=? AND user_id=?');$stmt->execute([$bank,$label,$holder,$last4,$id,$userId]);}else{$stmt=$pdo->prepare('INSERT INTO bank_cards(user_id,bank_name,card_label,holder_name,last4) VALUES(?,?,?,?,?)');$stmt->execute([$userId,$bank,$label,$holder,$last4]);}
    } elseif ($action==='delete_card') {
      $stmt=$pdo->prepare('DELETE FROM bank_cards WHERE id=? AND user_id=?');$stmt->execute([(int)($_POST['id']??0),$userId]);
    } elseif ($action==='save_goal') {
      $id=(int)($_POST['id']??0);$title=trim((string)($_POST['title']??''));$target=filter_var(latinDigits((string)($_POST['target_amount']??'')),FILTER_VALIDATE_INT);$current=filter_var(latinDigits((string)($_POST['current_amount']??'0')),FILTER_VALIDATE_INT);$dateInput=trim((string)($_POST['target_date']??''));$date=$dateInput===''?null:parseJalaliDate($dateInput);
      if(mb_strlen($title)<2||mb_strlen($title)>150||!$target||$target<1||$target>999999999999||$current===false||$current<0||$current>$target||($dateInput!==''&&$date===null))throw new RuntimeException('عنوان، مبلغ هدف، پس‌انداز فعلی یا تاریخ را بررسی کن.');
      if($id>0){$stmt=$pdo->prepare('UPDATE savings_goals SET title=?,target_amount=?,current_amount=?,target_date=? WHERE id=? AND user_id=?');$stmt->execute([$title,$target,$current,$date,$id,$userId]);}else{$stmt=$pdo->prepare('INSERT INTO savings_goals(user_id,title,target_amount,current_amount,target_date) VALUES(?,?,?,?,?)');$stmt->execute([$userId,$title,$target,$current,$date]);}
    } elseif ($action==='delete_goal') {
      $stmt=$pdo->prepare('DELETE FROM savings_goals WHERE id=? AND user_id=?');$stmt->execute([(int)($_POST['id']??0),$userId]);
    } elseif ($action==='save_budget') {
      $category=trim((string)($_POST['category']??'')); $limit=filter_var(latinDigits((string)($_POST['monthly_limit']??'')),FILTER_VALIDATE_INT);
      if ($category==='' || !$limit || $limit<1) throw new RuntimeException('دسته و سقف بودجه معتبر وارد کن.');
      $stmt=$pdo->prepare('INSERT INTO budgets(user_id,category,monthly_limit) VALUES(?,?,?) ON DUPLICATE KEY UPDATE monthly_limit=VALUES(monthly_limit)'); $stmt->execute([$userId,mb_substr($category,0,80),$limit]);
    } elseif ($action==='delete_budget') {
      $stmt=$pdo->prepare('DELETE FROM budgets WHERE id=? AND user_id=?'); $stmt->execute([(int)($_POST['id']??0),$userId]);
    }
  } catch (Throwable $e) { $error=$e instanceof RuntimeException?$e->getMessage():'عملیات انجام نشد. ورودی‌ها را بررسی کن.'; }
  if (($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='XMLHttpRequest' && in_array(($_POST['action']??''),['toggle_task'],true)) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>!$error,'error'=>$error],JSON_UNESCAPED_UNICODE); exit; }
  if (!$error) redirectHome();
}
