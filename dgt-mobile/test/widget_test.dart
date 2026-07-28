import 'package:flutter_test/flutter_test.dart';

import 'package:dgt_mobile_app/main.dart';

void main() {
  test('KIUQ mobile shell uses the hosted app URL', () {
    expect(appBaseUrl, startsWith('https://'));
    expect(appBaseUrl, contains('hostingersite.com'));
  });
}
