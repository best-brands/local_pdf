# local_pdf

By default CS-Cart does HTML to PDF conversion on their own servers,
which in many cases is not feasible because of international laws (as
these servers are based in russia). The data in these orders is not only
send to russia, but is also not masked in any way. This means that your
customers' data is temporarily in Russia, which certainly raises a
variety of questions for most users.

Therefore I have created a simple add-on that you can use to generate
your PDF's locally. Just make sure WkHtmlToPdf is installed locally
and that you have permissions to use the according PHP functions.

## Installation

Just download the zip and extract it in the root of your directory. Do
note that this changes core files so after updates you might have to
re-upload the addon.
