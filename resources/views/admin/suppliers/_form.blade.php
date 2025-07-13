<x-input name="name" label="Nama Supplier" :value="$supplier->name ?? ''" required />
<x-input name="contact_person" label="Kontak Person" :value="$supplier->contact_person ?? ''" />
<x-input name="phone" label="Telepon" :value="$supplier->phone ?? ''" />
<x-input type="email" name="email" label="Email" :value="$supplier->email ?? ''" />
<x-input type="textarea" name="address" label="Alamat" :value="$supplier->address ?? ''" />